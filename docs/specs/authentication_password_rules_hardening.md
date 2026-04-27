# Feature: Authentication password rules hardening

Goal
Describe the objective.

Harden server-side password validation by enforcing stronger complexity rules in one shared place so registration, password reset confirmation, and profile password change all apply the same policy.

Behavior
Describe expected behavior.

- Password validation is centralized in the existing shared helper in `AuthService`.
- A password is accepted only if it meets the agreed rule set (minimum length plus required character classes).
- The same server-side rule is applied consistently to:
  - registration
  - password reset confirmation (new password)
  - profile password change
- Invalid passwords are rejected with clear validation feedback; valid passwords continue through existing flows.

Constraints
Technical or security constraints.

- Keep this slice backend-first: no mandatory UI copy changes in this spec.
- No new dependencies or password-strength libraries.
- Reuse current architecture boundaries (controller -> service -> repository); no routing redesign.
- Keep generic and safe error behavior (no sensitive leakage).
- Rule definition must be explicit and shared from one source to avoid drift across auth flows.

Success Criteria
How we know the feature works.

- Server-side checks reject passwords that miss any required rule component.
- Server-side checks accept passwords that satisfy all required components.
- Register flow enforces the new rule.
- Password reset confirm flow enforces the new rule.
- Profile password change flow enforces the new rule.
- Existing successful auth behavior remains unchanged outside password rule strictness.

## Implementation Plan

1 define the exact password rule constants in `AuthService` (minimum length and required character classes)
2 update the shared password validation helper in `AuthService` to enforce the full rule set
3 keep the helper output shape and map new failure conditions to existing password error keys
4 verify `AuthService::register` still uses the shared helper path for password validation
5 verify password reset confirm service path uses the same shared helper without parallel validation logic
6 verify profile password change service path uses the same shared helper without parallel validation logic

## Tests

**Test cases**

- **Success**
  - S1: `POST /register` with a password that satisfies all required classes succeeds (`302`).
  - S2: `POST /password-reset/confirm` with a valid token and a compliant password succeeds (`302`), then login with that password succeeds.
  - S3: Authenticated `POST /settings/profile/password` with compliant new password succeeds (`302`).
- **Failure**
  - F1: `POST /register` with missing required class (e.g. no special char) fails (`200`) with password validation error.
  - F2: `POST /password-reset/confirm` with valid token but weak password fails (`200`), password remains unchanged.
  - F3: Authenticated `POST /settings/profile/password` with weak new password fails (`200`), password remains unchanged.
- **Edge**
  - E1: Password exactly at minimum length and otherwise compliant is accepted.
  - E2: Spaces around submitted password are not treated as satisfying required classes by themselves.
  - E3: Same weak password pattern is rejected consistently across register, reset confirm, and profile change.

**Test Setup (authentication)**

```bash
set -euo pipefail

BASE=${BASE:-http://127.0.0.1:8080}
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}

UE="pw_rules_$(date +%s)@example.test"
USER="pw_rules_$(date +%s)"
OLD_PASS='OldPass123!'
GOOD_PASS='GoodPass123!'
WEAK_PASS='weakpass'
COOKIE='cookies.txt'

rm -f "$COOKIE"

# Register baseline user
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -c "$COOKIE" \
  -d "email=$UE" \
  -d "username=$USER" \
  -d "password=$OLD_PASS" \
  -d "confirm_password=$OLD_PASS"

# Mark user verified for login/profile/reset checks
sqlite3 "$DATABASE_PATH" "UPDATE users SET email_verified = 1 WHERE email = '$UE';"

# Login baseline session
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/login" \
  -c "$COOKIE" \
  -d "username=$USER" \
  -d "password=$OLD_PASS"

# Seed reset token for confirm flow
RAW_TOKEN='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
TOKEN_HASH=$(php -r "echo hash('sha256', '$RAW_TOKEN');")
EXP_FUTURE=$(php -r "echo date('Y-m-d H:i:s', time()+3600);")
sqlite3 "$DATABASE_PATH" "UPDATE users SET password_reset_token='$TOKEN_HASH', password_reset_expires_at='$EXP_FUTURE' WHERE email='$UE';"
```

**Execute tests**

```bash
# S1: register with compliant password (expect 302)
E1="pw_ok_$(date +%s)@example.test"
U1="pw_ok_$(date +%s)"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$E1" -d "username=$U1" \
  -d "password=$GOOD_PASS" -d "confirm_password=$GOOD_PASS"

# F1: register weak password (expect 200)
E2="pw_bad_$(date +%s)@example.test"
U2="pw_bad_$(date +%s)"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$E2" -d "username=$U2" \
  -d "password=$WEAK_PASS" -d "confirm_password=$WEAK_PASS"

# S2: reset confirm with compliant password (expect 302)
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/password-reset/confirm" \
  -d "token=$RAW_TOKEN" \
  -d "password=$GOOD_PASS" \
  -d "confirm_password=$GOOD_PASS"

# S2 follow-up: login with new password (expect 302)
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/login" \
  -d "username=$USER" -d "password=$GOOD_PASS"

# Re-seed token for weak reset checks
EXP_FUTURE=$(php -r "echo date('Y-m-d H:i:s', time()+3600);")
sqlite3 "$DATABASE_PATH" "UPDATE users SET password_reset_token='$TOKEN_HASH', password_reset_expires_at='$EXP_FUTURE' WHERE email='$UE';"

# F2: reset confirm weak password (expect 200)
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/password-reset/confirm" \
  -d "token=$RAW_TOKEN" \
  -d "password=$WEAK_PASS" \
  -d "confirm_password=$WEAK_PASS"

# S3: profile password change compliant password (expect 302)
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/settings/profile/password" \
  -b "$COOKIE" \
  -d "current_password=$GOOD_PASS" \
  -d "password=NextPass123!" \
  -d "confirm_password=NextPass123!"

# F3: profile password change weak password (expect 200)
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/settings/profile/password" \
  -b "$COOKIE" \
  -d "current_password=NextPass123!" \
  -d "password=$WEAK_PASS" \
  -d "confirm_password=$WEAK_PASS"

# E1: minimum-length compliant password should pass on register (expect 302)
E3="pw_min_$(date +%s)@example.test"
U3="pw_min_$(date +%s)"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$E3" -d "username=$U3" \
  -d "password=Aa1!bcde" -d "confirm_password=Aa1!bcde"
```
