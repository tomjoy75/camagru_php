# Feature: `authentication_profile_edit_email`

Goal  
Allow an authenticated user to change the email address on their profile with safe validation and clear feedback, as the email-only slice of profile editing (`#51`).

Behavior  
- Authenticated user can access profile settings and submit a new email.
- Server validates required email format and uniqueness before saving.
- Valid and unused email updates the current user record and returns success feedback.
- On successful email change, account is set back to unverified and a fresh confirmation token is generated for the new email.
- The current email-change request still succeeds normally; subsequent login attempts follow the existing verified-email login rule until reconfirmation.
- Invalid format or already-used email does not update data and returns safe error feedback.
- Existing authentication/session behavior remains unchanged after update.

Constraints  
- Scope is email-change only (no username/password changes in this feature).
- Keep MVC boundaries: controller handles request/response, service contains business rules, repository handles persistence, view renders HTML only.
- Use prepared statements and existing validation/security conventions.
- Mutualize email-format validation used by registration and profile email-change through one shared private helper method (no duplicated format-check logic).
- No new dependencies or framework additions.
- Verification-state policy is fixed: after successful email change, set account to unverified and generate a fresh confirmation token for the new email.

Success Criteria  
- Authenticated `POST` with a valid, unused email updates the user email in DB.
- After successful email change, DB reflects `email_verified = 0` and a new non-empty `confirmation_token` for that user.
- Authenticated `POST` with invalid or duplicate email leaves DB unchanged and shows an error.
- Unauthenticated access to profile update flow is safely rejected (existing auth guard behavior).
- Post-change authentication follows the existing verified-email login rule (updated account cannot log in until reconfirmed).

## Implementation Plan

1 add authenticated `GET`/`POST` profile-email route wiring to existing profile settings flow
2 parse and normalize submitted email input in controller/service entry point
3 add or reuse one shared private helper for email format validation, then validate uniqueness against existing users
4 update current user email through repository prepared statement on valid input
5 keep existing safe flash + redirect response paths for success and validation failure
6 after successful email update, set `email_verified` to unverified and persist a fresh confirmation token in the same update path

## Tests

**Test cases**

- **Success**
  - S1: Authenticated user submits a valid, unused email; update succeeds and persists.
  - S1b: Same successful update also sets `email_verified = 0` and rotates `confirmation_token` (new non-empty value).
  - S2: `GET` profile settings shows current email value before change.
- **Failure**
  - F1: Invalid email format is rejected; DB email remains unchanged.
  - F2: Email already used by another account is rejected; DB email remains unchanged.
  - F3: Unauthenticated `GET`/`POST` to profile email flow is rejected via existing auth guard.
  - F4: After successful email change, **`POST /login`** with **username** (unchanged) + password is blocked by the existing verified-email rule until reconfirmation.
- **Edge**
  - E1: Email with surrounding spaces is normalized then validated (accepted only if valid after normalization).
  - E2: Case-variant duplicate (if app treats email case-insensitively) is rejected consistently.
  - E3: Registration and profile edit both reject the same invalid format via the shared private helper behavior.

**Test Setup (authentication)**

```bash
BASE=http://127.0.0.1:8080
DATABASE_PATH=database/camagru.db
rm -f cookies.txt cookies2.txt

STAMP=$(date +%s)
# user A register + login (target user for email change)
UA="profile_email_a_${STAMP}@example.com"
U_USERA="profilea${STAMP}"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$UA" \
  -d "username=$U_USERA" \
  -d "password=Password123!" \
  -d "confirm_password=Password123!"
# ensure user A can authenticate under verified-email login rule
sqlite3 "$DATABASE_PATH" "UPDATE users SET email_verified = 1 WHERE email = '$UA';"
curl -s -o /dev/null -w "%{http_code}\n" -c cookies.txt -X POST "$BASE/login" \
  -d "username=$U_USERA" \
  -d "password=Password123!"

# user B register (used to create duplicate-email scenario)
UB="profile_email_b_${STAMP}@example.com"
U_USERB="profileb${STAMP}"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$UB" \
  -d "username=$U_USERB" \
  -d "password=Password123!" \
  -d "confirm_password=Password123!"
# keep setup identities available for Execute tests block
export UA UB U_USERA U_USERB
```

**Execute tests**

```bash
BASE=http://127.0.0.1:8080
DATABASE_PATH=database/camagru.db
: "${UA:?Run Test Setup (authentication) in the same shell first}"
: "${UB:?Run Test Setup (authentication) in the same shell first}"
: "${U_USERA:?Run Test Setup (authentication) in the same shell first}"
: "${U_USERB:?Run Test Setup (authentication) in the same shell first}"

# S2: authenticated GET settings profile
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt "$BASE/settings/profile"

# S1: valid unused email update
NEW_OK="profile_email_ok_$(date +%s)@example.com"
USER_ID="$(sqlite3 "$DATABASE_PATH" "SELECT id FROM users WHERE email='$UA' LIMIT 1;")"
OLD_TOKEN="$(sqlite3 "$DATABASE_PATH" "SELECT confirmation_token FROM users WHERE id=$USER_ID;")"
curl -s -i -b cookies.txt -X POST "$BASE/settings/profile/email" \
  -d "email=$NEW_OK" | sed -n '1,20p'
sqlite3 "$DATABASE_PATH" "SELECT COUNT(*) FROM users WHERE email='$NEW_OK';"
sqlite3 "$DATABASE_PATH" "SELECT email_verified, LENGTH(COALESCE(confirmation_token, '')) > 0 FROM users WHERE id=$USER_ID;"
NEW_TOKEN="$(sqlite3 "$DATABASE_PATH" "SELECT confirmation_token FROM users WHERE id=$USER_ID;")"
test "$OLD_TOKEN" != "$NEW_TOKEN" && echo "S1b OK"

# F1: invalid format rejected
OLD_COUNT="$(sqlite3 "$DATABASE_PATH" "SELECT COUNT(*) FROM users WHERE email='bad-email';")"
curl -s -i -b cookies.txt -X POST "$BASE/settings/profile/email" \
  -d "email=bad-email" | sed -n '1,20p'
NEW_COUNT="$(sqlite3 "$DATABASE_PATH" "SELECT COUNT(*) FROM users WHERE email='bad-email';")"
test "$OLD_COUNT" = "$NEW_COUNT" && echo "F1 OK"

# F2: duplicate email rejected
OLD_DUP="$(sqlite3 "$DATABASE_PATH" "SELECT COUNT(*) FROM users WHERE email='$UB';")"
curl -s -i -b cookies.txt -X POST "$BASE/settings/profile/email" \
  -d "email=$UB" | sed -n '1,20p'
NEW_DUP="$(sqlite3 "$DATABASE_PATH" "SELECT COUNT(*) FROM users WHERE email='$UB';")"
test "$OLD_DUP" = "$NEW_DUP" && echo "F2 OK"

# F3: unauthenticated access rejected
curl -s -o /dev/null -w "%{http_code}\n" "$BASE/settings/profile"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/settings/profile/email" -d "email=x@example.com"

# F4: login blocked until reconfirmation after successful email change (username unchanged; email unverified)
rm -f cookies-post-change.txt
curl -s -i -c cookies-post-change.txt -X POST "$BASE/login" \
  -d "username=$U_USERA" \
  -d "password=Password123!" | sed -n '1,20p'

# E1: trim/normalize behavior (result depends on final normalization rule)
TRIMMED="   profile_email_trim_$(date +%s)@example.com   "
curl -s -i -b cookies.txt -X POST "$BASE/settings/profile/email" -d "email=$TRIMMED" | sed -n '1,20p'

# E3: registration and profile should both reject same invalid format
curl -s -i -X POST "$BASE/register" \
  -d "email=bad-email" \
  -d "username=badfmt$(date +%s)" \
  -d "password=Password123!" \
  -d "confirm_password=Password123!" | sed -n '1,20p'
curl -s -i -b cookies.txt -X POST "$BASE/settings/profile/email" \
  -d "email=bad-email" | sed -n '1,20p'
```
