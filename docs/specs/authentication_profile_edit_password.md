# Feature: `authentication_profile_edit_password`

Goal  
Allow an authenticated user to change the account password from profile settings with secure validation and clear feedback, as the password-only profile editing slice (`#52`).

Behavior  
- Authenticated user can submit current password, new password, and password confirmation from profile settings.
- Server requires a correct current password before applying any change.
- Server validates new password rules and confirmation match before saving.
- On valid input, server stores only a new secure password hash and returns success feedback.
- Invalid current password or invalid new password input does not update data and returns safe error feedback.
- Existing session/authentication behavior remains coherent after update.

Constraints  
- Scope is password-change only (no username/email updates in this feature).
- Keep MVC boundaries: controller handles request/response, service contains business rules, repository handles persistence, view renders HTML only.
- Use existing password security conventions (`password_verify` for current password check, `password_hash` for new password storage).
- Mutualize new-password rule validation in one shared private helper in `AuthService`, used by both `register()` and password change (no duplicated length/policy checks; confirmation matching stays at call sites or is composed consistently).
- Use prepared statements and existing validation/security conventions.
- Keep generic/safe error feedback (do not expose sensitive authentication details).
- No new dependencies or framework additions.

Success Criteria  
- Authenticated valid request with correct current password and valid new password updates `password_hash` in DB.
- New stored hash validates with `password_verify(new_password, password_hash)` and differs from the old hash.
- Authenticated request with wrong current password keeps DB unchanged and returns safe error feedback.
- Authenticated request with invalid new password (or confirmation mismatch) keeps DB unchanged and returns validation feedback, with new-password rules equivalent to registration via the shared private helper.
- Unauthenticated access to password update flow is safely rejected by existing auth guard behavior.

## Implementation Plan

1 add authenticated `POST` route `/settings/profile/password` wired to settings controller handler
2 add password change form in `settings_profile.php` (current password, new password, confirmation) posting to that route
3 in controller, parse POST fields, call auth service, map result to safe flash messages, redirect to `/settings/profile`
4 in `AuthService`, extract shared private new-password validation helper; refactor `register()` to use it; add password change path: verify current with `password_verify`, validate new via helper + confirmation match, return structured field errors
5 in `UserRepository`, update `password_hash` for the current user id via prepared statement

## Tests

**Test cases**

- **Success**
  - S1: Authenticated user submits correct current password, valid new password, and matching confirmation; `password_hash` updates in DB and differs from previous value.
  - S2: After S1, `POST /login` with the same email and the **new** password succeeds (e.g. redirect) for a verified user.
  - S3: After S1, `POST /login` with the **old** password fails safely (no authenticated session for that attempt).
- **Failure**
  - F1: Wrong current password; DB `password_hash` unchanged; safe error feedback.
  - F2: New password fails shared rule validation (e.g. shorter than minimum length); DB unchanged.
  - F3: New password and confirmation do not match; DB unchanged.
  - F4: Unauthenticated `POST /settings/profile/password` is rejected by auth guard.
- **Edge**
  - E1: New password exactly at minimum allowed length is accepted when current password is correct and confirmation matches.
  - E2: `POST /register` with too-short password and authenticated `POST /settings/profile/password` with too-short new password both fail consistently (shared private helper).

**Test Setup (authentication)**

```bash
BASE=http://127.0.0.1:8080
DATABASE_PATH=database/camagru.db
rm -f cookies.txt

# user for password change (initial password must match curl payloads below)
UA="profile_pwd_a_$(date +%s)@example.com"
INITIAL_PW="Password123!"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$UA" \
  -d "username=profilepwda$(date +%s)" \
  -d "password=$INITIAL_PW" \
  -d "confirm_password=$INITIAL_PW"
sqlite3 "$DATABASE_PATH" "UPDATE users SET email_verified = 1 WHERE email = '$UA';"
curl -s -o /dev/null -w "%{http_code}\n" -c cookies.txt -X POST "$BASE/login" \
  -d "email=$UA" \
  -d "password=$INITIAL_PW"

export UA INITIAL_PW
```

**Execute tests**

```bash
BASE=http://127.0.0.1:8080
DATABASE_PATH=database/camagru.db
: "${UA:?Run Test Setup (authentication) in the same shell first}"
: "${INITIAL_PW:?Run Test Setup (authentication) in the same shell first}"
# Form field names must match implementation (expected: current_password, password, confirm_password for the new password pair)

USER_ID="$(sqlite3 "$DATABASE_PATH" "SELECT id FROM users WHERE email='$UA' LIMIT 1;")"
OLD_HASH="$(sqlite3 "$DATABASE_PATH" "SELECT password_hash FROM users WHERE id=$USER_ID;")"

# F1: wrong current password
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/settings/profile/password" \
  -d "current_password=wrong" \
  -d "password=NewPassword456!" \
  -d "confirm_password=NewPassword456!"
HASH_AFTER_F1="$(sqlite3 "$DATABASE_PATH" "SELECT password_hash FROM users WHERE id=$USER_ID;")"
test "$OLD_HASH" = "$HASH_AFTER_F1" && echo "F1 OK"

# F2: new password too short (below registration minimum)
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/settings/profile/password" \
  -d "current_password=$INITIAL_PW" \
  -d "password=short" \
  -d "confirm_password=short"
HASH_AFTER_F2="$(sqlite3 "$DATABASE_PATH" "SELECT password_hash FROM users WHERE id=$USER_ID;")"
test "$OLD_HASH" = "$HASH_AFTER_F2" && echo "F2 OK"

# F3: confirmation mismatch
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/settings/profile/password" \
  -d "current_password=$INITIAL_PW" \
  -d "password=NewPassword456!" \
  -d "confirm_password=NewPassword455!"
HASH_AFTER_F3="$(sqlite3 "$DATABASE_PATH" "SELECT password_hash FROM users WHERE id=$USER_ID;")"
test "$OLD_HASH" = "$HASH_AFTER_F3" && echo "F3 OK"

# S1: successful change
NEW_PW="NewPassword456!"
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/settings/profile/password" \
  -d "current_password=$INITIAL_PW" \
  -d "password=$NEW_PW" \
  -d "confirm_password=$NEW_PW"
NEW_HASH="$(sqlite3 "$DATABASE_PATH" "SELECT password_hash FROM users WHERE id=$USER_ID;")"
test "$OLD_HASH" != "$NEW_HASH" && echo "S1 OK"

# S2 / S3: login with new vs old password
curl -s -o /dev/null -w "login_new:%{http_code}\n" -X POST "$BASE/login" -d "email=$UA" -d "password=$NEW_PW"
curl -s -o /dev/null -w "login_old:%{http_code}\n" -X POST "$BASE/login" -d "email=$UA" -d "password=$INITIAL_PW"

# E2: register vs profile — short new password rejected both (session still authenticated; DB password is $NEW_PW)
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=shortpw$(date +%s)@example.com" \
  -d "username=shortpw$(date +%s)" \
  -d "password=bad" \
  -d "confirm_password=bad"
HASH_BEFORE_E2="$(sqlite3 "$DATABASE_PATH" "SELECT password_hash FROM users WHERE id=$USER_ID;")"
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/settings/profile/password" \
  -d "current_password=$NEW_PW" \
  -d "password=bad" \
  -d "confirm_password=bad"
test "$HASH_BEFORE_E2" = "$(sqlite3 "$DATABASE_PATH" "SELECT password_hash FROM users WHERE id=$USER_ID;")" && echo "E2 OK"

# E1: minimum-length new password
MIN8="abcdefgh"
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/settings/profile/password" \
  -d "current_password=$NEW_PW" \
  -d "password=$MIN8" \
  -d "confirm_password=$MIN8"

# F4: unauthenticated POST
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/settings/profile/password" \
  -d "current_password=x" -d "password=y" -d "confirm_password=y"
```
