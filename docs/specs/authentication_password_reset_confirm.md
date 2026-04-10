# Feature: `authentication_password_reset_confirm`

Tracks [GitHub #54](https://github.com/tomjoy75/camagru_php/issues/54) (password reset **phase 2 — consume token and set new password**). Depends on [GitHub #53](https://github.com/tomjoy75/camagru_php/issues/53) / `docs/specs/authentication_password_reset_request.md` (reset link and `password_reset_*` columns). Parent context: [#13](https://github.com/tomjoy75/camagru_php/issues/13).

## Goal

Let a **non-authenticated** user who received a reset email complete the flow: open the link, enter a **new password** (with confirmation), and on success have `**password_hash` updated**, reset state **cleared**, and a clear path back to **login**. Invalid, expired, or reused tokens must fail **safely** without leaking whether an account exists.

## Behavior

- **Link shape:** Consumes the same URL as sent in phase 1: `**GET /password-reset/confirm?token=`** plus a **64-character hex** token (`rawurldecode` as needed). Malformed tokens (wrong length/charset) are treated like unknown tokens.
- **GET with valid token:** Token is verified against the DB by hashing the raw token with **SHA-256** (hex), matching the value stored in `**password_reset_token`**, and ensuring `**password_reset_expires_at**` is **strictly after** the current time (same datetime string convention as #53). If valid, render a **password reset form**: new password, confirm password, submit. The token must be carried to **POST** (e.g. hidden field **or** same query on form `action`) so the server can re-verify on submit.
- **GET with missing, invalid, expired, or non-matching token:** **No** database writes. Show a **single neutral outcome** (page or message) that does not reveal whether the email existed, whether a reset was requested, or whether the token was expired vs wrong (wording can match the generic style used elsewhere for safe failures).
- **POST:** Read token + new password + confirmation; **re-validate** token the same way as GET (hash + expiry + row lookup). Apply the **same password rules** as registration / profile password change (**reuse** existing `AuthService` validation for new passwords, e.g. minimum length and match). On validation errors, re-render the form with **field errors** only if the token is still valid; if the token is no longer valid mid-flow, fall back to the **same neutral failure** as bad GET.
- **POST success:** Compute `**password_hash`** with `**password_hash(..., PASSWORD_DEFAULT)**`, persist with `**UserRepository**` (existing or extended update helper), then **clear** `**password_reset_token`** and `**password_reset_expires_at**` for that user so the link is **single-use**. Redirect (PRG) to a **success** view or login with a short, escaped success message; **do not** auto-log the user in unless explicitly specified later.
- **MVC:** Router registers **GET** and **POST** for `/password-reset/confirm` (or the exact path already used in the phase-1 mail template — keep them identical); thin **AuthController** (or same controller as #53); rules orchestrated in **AuthService**; SQL only in **UserRepository**; views HTML-only with `**htmlspecialchars`** for dynamic text.
- **Out of scope:** rate limiting, CAPTCHA, optional “logged-in user cannot use reset link” edge policy unless the codebase already enforces it elsewhere.

## Constraints

- **PHP standard library only**; **prepared statements** for all SQL; **no** new Composer dependencies.
- **Security:** Compare stored token hash using `**hash_equals`** (or equivalent timing-safe compare). **Do not** log full tokens. **Do not** expose stack traces or SQL errors to the client.
- **Password storage:** Only `**password_hash`** / `**password_verify**` for credentials; never store plaintext passwords.
- **Reuse-first:** Prefer **extending** `UserRepository` and `**AuthService`** (e.g. reuse `**validateNewPasswordRules**` and confirmation match pattern from `**changePassword**` without duplicating rules). **Do not** reuse `**confirmation_token`** machinery for reset; reset stays on `**password_reset_***` columns only.
- **Explicitly out of scope:** changing the **request** flow (#53), email content, or login blocking rules beyond what already exists.

## Success Criteria

- **S1:** After a **valid** reset link **GET**, the form is shown and a **POST** with matching passwords that satisfy rules updates `**password_hash`**, clears `**password_reset_token**` and `**password_reset_expires_at**`, and the user can **log in** with the new password (old password no longer works).
- **S2:** **GET** or **POST** with **wrong** / **malformed** / **missing** token → **no** password change on any user; **non-500** response; **neutral** user-facing message.
- **S3:** **GET** or **POST** with an **expired** token (per stored `**password_reset_expires_at`**) → **no** password change; **neutral** message (indistinguishable from wrong token if so chosen).
- **S4:** After a **successful** reset, **reusing** the same link (**GET** or **POST**) does **not** change any password again (token cleared; behaves like invalid token).
- **S5:** **POST** with valid token but **weak** or **mismatched** passwords → **no** `password_hash` update; **no** reset fields cleared; user sees **validation errors** on the form.
- **S6:** Unsafe methods to the confirm path (if the router only defines GET/POST) are rejected consistently with the rest of the app (e.g. **404**), with **no** DB mutation.

## Implementation Plan

1 register `GET` and `POST` `/password-reset/confirm` in `src/routes/index.php` calling `AuthController` (same path as phase-1 mail)
2 add `UserRepository::findUserIdByPasswordResetTokenHashIfValid(string $tokenHash): ?int` via prepared `SELECT` matching `password_reset_token` and `password_reset_expires_at` still in the future
3 add `UserRepository::updatePasswordHashAndClearPasswordResetByUserId(int $userId, string $passwordHash): bool` (set `password_hash`, null/clear `password_reset_token` and `password_reset_expires_at`)
4 add `AuthService::resolvePasswordResetUserIdFromRawToken(string $rawToken): ?int` (64-hex check, `hash('sha256', …)`, delegate to step 2)
5 add `AuthService::completePasswordResetForUserId(int $userId, string $newPassword, string $confirmPassword): array` reusing existing new-password rules + confirmation match, then `password_hash` + step 3; return field errors or empty on success
6 add `AuthController` GET/POST actions: read `token` from query or POST, call steps 4–5, render neutral invalid view, form with hidden token + errors, or `302` to a success route/view after POST success
7 add views `password_reset_confirm_form.php`, `password_reset_confirm_invalid.php`, `password_reset_confirm_success.php` (HTML only, `htmlspecialchars` on dynamic text)

## Tests

**Test cases**

- **Success**
  - **S1:** `GET /password-reset/confirm?token=` with a **valid** 64-hex token (hash + future `password_reset_expires_at` in DB) returns **200** and HTML containing the reset form; `POST` with same token and valid matching passwords returns **302** (PRG) to the success URL; `password_reset_*` cleared; **`POST /login`** with same email + **new** password succeeds; **old** password rejected.
- **Failure**
  - **F1:** `GET` **missing** `token` → **non-500**, neutral invalid outcome; **no** user row mutated.
  - **F2:** `GET` / `POST` with **malformed** token (wrong length or non-hex) → **non-500**, neutral invalid; **no** `password_hash` change.
  - **F3:** `GET` / `POST` with **unknown** (non-matching) 64-hex token → **non-500**, neutral invalid; **no** `password_hash` change.
  - **F4:** `GET` / `POST` with **expired** `password_reset_expires_at` → **non-500**, neutral invalid; **no** `password_hash` change.
- **Edge**
  - **E1:** After **successful** reset, **repeat** `GET` with the **same** token → neutral invalid; `password_hash` unchanged on second attempt.
  - **E2:** `POST` with **valid** token but **too-short** or **mismatched** passwords → **200** (form again) with validation errors; reset columns **unchanged**; `password_hash` unchanged.
  - **E3:** **Unsupported method** (e.g. `PUT`) to `/password-reset/confirm` → **404** (or router-consistent rejection); **no** DB mutation from that request alone.

**Test setup (anonymous + seeded reset token)**

The DB stores only the **SHA-256 hex hash** of the raw token (see #53). Seed a known raw token and hash with PHP so bytes match the app.

```bash
rm -f cookies.txt
BASE=http://127.0.0.1:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
UE="reset_confirm_$(date +%s)@example.com"
USER=resetconf$(date +%s)
OLD_PASS='OldPassword123!'
NEW_PASS='NewPassword123!'

# Register + mark verified (same pattern as password-reset request spec)
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$UE" \
  -d "username=$USER" \
  -d "password=$OLD_PASS" \
  -d "confirm_password=$OLD_PASS"
sqlite3 "$DATABASE_PATH" "UPDATE users SET email_verified = 1, confirmation_token = NULL WHERE email = '$UE';"

# Raw 64-hex token + hash exactly as PHP: hash('sha256', $rawToken, false)
RAW_TOKEN=$(openssl rand -hex 32)
TOKEN_HASH=$(php -r 'echo hash("sha256", $argv[1], false);' "$RAW_TOKEN")
EXP_FUTURE='2099-01-01 00:00:00'
sqlite3 "$DATABASE_PATH" "UPDATE users SET password_reset_token='$TOKEN_HASH', password_reset_expires_at='$EXP_FUTURE' WHERE email = '$UE';"
```

**Execute tests**

Use the same shell so `$BASE`, `$DATABASE_PATH`, `$UE`, `$OLD_PASS`, `$NEW_PASS`, `$RAW_TOKEN` are set. Adjust **POST field names** (`token`, `password`, `confirm_password`) and the **success redirect path** if the implementation differs.

```bash
# S1: form loads
curl -s -o /dev/null -w "%{http_code}\n" "$BASE/password-reset/confirm?token=$RAW_TOKEN"

# S1: submit new password (expect 302 — follow with -L if checking final page)
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/password-reset/confirm" \
  -d "token=$RAW_TOKEN" \
  -d "password=$NEW_PASS" \
  -d "confirm_password=$NEW_PASS"

# S1: reset columns cleared
sqlite3 "$DATABASE_PATH" "SELECT (password_reset_token IS NULL OR password_reset_token = '') AND (password_reset_expires_at IS NULL OR password_reset_expires_at = '') FROM users WHERE email = '$UE';"

# S1: login with new password (expect 302 to post-login destination)
curl -s -o /dev/null -w "%{http_code}\n" -c cookies.txt -X POST "$BASE/login" \
  -d "email=$UE" \
  -d "password=$NEW_PASS"

# E1: same reset link after success — token consumed; expect neutral invalid (non-500)
curl -s -o /dev/null -w "%{http_code}\n" "$BASE/password-reset/confirm?token=$RAW_TOKEN"

# F1: missing token
curl -s -o /dev/null -w "%{http_code}\n" "$BASE/password-reset/confirm"

# F2: malformed token
curl -s -o /dev/null -w "%{http_code}\n" "$BASE/password-reset/confirm?token=nothex"

# F3: wrong 64-hex token
curl -s -o /dev/null -w "%{http_code}\n" "$BASE/password-reset/confirm?token=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

# F4: expired token (re-seed RAW_TOKEN / TOKEN_HASH for an isolated row or restore UE from setup)
sqlite3 "$DATABASE_PATH" "UPDATE users SET email_verified = 1, password_reset_token='$TOKEN_HASH', password_reset_expires_at='2000-01-01 00:00:00' WHERE email = '$UE';"
# If S1 already cleared the token, re-run Test setup for a fresh $UE or re-seed hash from a new RAW_TOKEN
curl -s -o /dev/null -w "%{http_code}\n" "$BASE/password-reset/confirm?token=$RAW_TOKEN"

# E2: valid token + bad passwords (re-seed token columns first if needed)
sqlite3 "$DATABASE_PATH" "UPDATE users SET password_reset_token='$TOKEN_HASH', password_reset_expires_at='$EXP_FUTURE' WHERE email = '$UE';"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/password-reset/confirm" \
  -d "token=$RAW_TOKEN" \
  -d "password=short" \
  -d "confirm_password=short"

# E3: unsupported method
curl -s -o /dev/null -w "%{http_code}\n" -X PUT "$BASE/password-reset/confirm"
```

Run **`php -S 127.0.0.1:8080 public/index.php`** from the **repository root** before curls. Re-run **Test setup** for a fresh user (or re-seed `password_reset_*`) if you need an **unconsumed** token after a full success path.

