# Feature: `authentication_profile_edit_username`

Goal  
Allow an authenticated user to update their username safely, as the smallest profile-editing slice from issue `#50`.

Behavior  
Provide an authenticated profile settings flow where a user submits a new username. The application validates the submitted username with the same rules used for registration, rejects invalid or already-used usernames with clear feedback, and persists a valid unique username. Email and password are not changed by this feature.

Constraints  
- Scope is limited to username change only (no email update, no password update).  
- Validation and uniqueness checks must remain server-side.  
- Follow existing MVC boundaries: controller handles request/response, service handles business logic, repository handles persistence, view renders feedback only.  
- Do not introduce new dependencies or new authentication flows.

Success Criteria  
- An authenticated user can update to a valid, available username and receives success feedback.  
- Invalid username input is rejected safely with user-facing error feedback.  
- A username already used by another account is rejected safely with user-facing error feedback.  
- Existing login/session behavior remains unchanged after username update.

## Implementation Plan

1 add authenticated settings route wiring for username update (GET form + POST submit)
2 add repository method to update username by user id with prepared statement
3 extract a private AuthService username validator and reuse it in register and username-update flow
4 add repository/service username uniqueness check (including conflict excluding current user) and apply it in register and username-update flow
5 implement controller POST flow for success/error flashes and safe redirect
6 render settings view form and feedback for username-only update

## Tests

**Test cases**

- **Success**
  - S1 authenticated user submits a valid unused username, receives success response, and DB username is updated.
  - S2 after successful update, login still works with the **new** username and same password; session behavior is unchanged.
- **Failure**
  - F1 unauthenticated GET/POST on profile username endpoints is rejected safely (redirect to login), with no DB update.
  - F2 invalid username format/length is rejected, with no DB update.
  - F3 already-used username is rejected, with no DB update.
- **Edge**
  - E1 submitting the current username is handled safely (no crash; unchanged DB and consistent feedback).
  - E2 wrong HTTP method on submit endpoint is rejected by router/controller (no mutation).

**Test Setup (authentication)**

```bash
BASE="http://127.0.0.1:8080"
DATABASE_PATH="database/camagru.db"
PROFILE_GET="/settings/profile"
PROFILE_POST="/settings/profile/username"

rm -f cookies_a.txt cookies_b.txt

STAMP=$(date +%s)
# user A register + login
A_EMAIL="profile_a_${STAMP}@example.com"
A_USERNAME="profile_a_${STAMP}"
A_PASSWORD="Password123"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$A_EMAIL" -d "username=$A_USERNAME" -d "password=$A_PASSWORD" -d "confirm_password=$A_PASSWORD"
sqlite3 "$DATABASE_PATH" "UPDATE users SET email_verified = 1 WHERE email = '$A_EMAIL';"
curl -s -o /dev/null -w "%{http_code}\n" -c cookies_a.txt -X POST "$BASE/login" \
  -d "username=$A_USERNAME" -d "password=$A_PASSWORD"

# user B for uniqueness conflict checks
B_EMAIL="profile_b_${STAMP}@example.com"
B_USERNAME="profile_b_${STAMP}"
B_PASSWORD="Password123"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$B_EMAIL" -d "username=$B_USERNAME" -d "password=$B_PASSWORD" -d "confirm_password=$B_PASSWORD"
sqlite3 "$DATABASE_PATH" "UPDATE users SET email_verified = 1 WHERE email = '$B_EMAIL';"
```

**Execute tests**

```bash
# S1: authenticated valid username update
NEW_USERNAME="profile_new_$(date +%s)"
curl -s -o /dev/null -w "%{http_code}\n" -b cookies_a.txt -X POST "$BASE$PROFILE_POST" -d "username=$NEW_USERNAME"
sqlite3 "$DATABASE_PATH" "SELECT username FROM users WHERE email = '$A_EMAIL';"

# S2: login still works after username update
rm -f cookies_a2.txt
curl -s -o /dev/null -w "%{http_code}\n" -c cookies_a2.txt -X POST "$BASE/login" \
  -d "username=$NEW_USERNAME" -d "password=$A_PASSWORD"

# F1: unauthenticated requests rejected, no update
curl -s -o /dev/null -w "%{http_code}\n" "$BASE$PROFILE_GET"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE$PROFILE_POST" -d "username=unauth_try"

# F2: invalid username rejected
curl -s -o /dev/null -w "%{http_code}\n" -b cookies_a.txt -X POST "$BASE$PROFILE_POST" -d "username=??"

# F3: duplicate username rejected (try B username on A)
curl -s -o /dev/null -w "%{http_code}\n" -b cookies_a.txt -X POST "$BASE$PROFILE_POST" -d "username=$B_USERNAME"
sqlite3 "$DATABASE_PATH" "SELECT username FROM users WHERE email = '$A_EMAIL';"

# E1: submit current username
curl -s -o /dev/null -w "%{http_code}\n" -b cookies_a.txt -X POST "$BASE$PROFILE_POST" -d "username=$NEW_USERNAME"

# E2: wrong method on submit endpoint
curl -s -o /dev/null -w "%{http_code}\n" "$BASE$PROFILE_POST"
```
