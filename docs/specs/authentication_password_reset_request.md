# Feature: `authentication_password_reset_request`

Tracks [GitHub #53](https://github.com/tomjoy75/camagru_php/issues/53) (password reset **phase 1 — request only**). Parent context: [#13](https://github.com/tomjoy75/camagru_php/issues/13). **Follow-up:** [#54](https://github.com/tomjoy75/camagru_php/issues/54) implements token consumption and password update (out of scope here).

Goal  
Let a **non-authenticated** user request a password reset by submitting an email: when a **matching, email-verified** account exists, persist a **time-limited reset token**, send **one** plain-text email with an absolute link containing the raw token, and always show the **same generic success feedback** so existence of the account is not leaked.

Behavior  
- **GET** shows a simple form: single **email** field + submit (and optional link back to login).
- **POST** accepts `email`; normalize (trim; case policy **must match** registration/login lookup, e.g. same normalization as `findByEmail`).
- **If** normalized email is **invalid format** → do **not** send mail; still show the **same generic success page/message** as the happy path (optional: separate safe validation message is allowed only if it does not reveal whether an account exists—simplest is one generic message for all outcomes).
- **If** no user exists for that email, or user exists but **`email_verified = 0`** → **no** email, **no** token write; response is still the **same generic success** as when a mail was sent.
- **If** a verified user exists → generate a cryptographically strong random token (e.g. 32 bytes hex, same order of magnitude as registration confirmation); **persist** token and **expiry** in the database using **columns dedicated to password reset** (do **not** reuse `confirmation_token` for this flow). **Recommended:** store only a **hash** of the token in the DB (e.g. SHA-256 of the raw token) and put the **raw** token only in the email link; if the project prefers parity with `confirmation_token` storage, document the choice in code comments and keep reset state isolated in separate columns.
- **Email:** Plain text; absolute URL = `MailEnv::validatedAppBaseUrl()` + canonical path for **phase 2** (e.g. `/password-reset/confirm?token=` + `rawurlencode($rawToken)`). Reuse **`APP_MAIL_FROM`** / optional **`APP_MAIL_REPLY_TO`** like other transactional mail. If mail env is invalid or `mail()` fails, the HTTP response for the user remains **generic success**; failures are **log-only** (markers consistent with e.g. `camagru_confirm_mail_*` / notification mail style). **Do not** expose stack traces.
- Issuing a **new** request **replaces** any previous reset token (and expiry) for that user so only one active reset link exists.
- **MVC:** Router wires routes; **AuthController** (or dedicated thin controller) handles GET/POST; **AuthService** (or small helper) owns rules; **UserRepository** owns SQL; views output HTML only.
- **Reuse-first:** Implement using existing `UserRepository::findByEmail()`, `MailEnv` for URL/from/reply-to, and **one new** `AuthService` entry point that uses the **existing private** `validateEmailFormat()` (no separate email-validator utility). Send mail by **extending** the same class that sends registration confirmation with a **dedicated** password-reset method (same env/recipient/token-regex/`rawurlencode`/`@mail()`/try-catch/`error_log` pattern); do **not** add a parallel mail class.

Constraints  
- PHP standard library only; **`mail()`** for send, aligned with existing mail services.
- Prepared statements for all SQL; no user-controlled SQL.
- **No** authentication required for this flow; **no** change to login/session behavior beyond what is explicitly specified.
- **Explicitly out of scope:** handling `GET` with `token`, new-password form, updating `password_hash`, clearing reset fields after success — that is **#54**.
- **Explicitly out of scope:** rate limiting / CAPTCHA (optional future hardening unless subject mandates).
- **Reuse-first implementation**
  - Use **`UserRepository::findByEmail()`** for lookup; do not duplicate email lookup queries.
  - Use **`MailEnv::validatedAppBaseUrl()`**, **`validatedMailFrom()`**, and **`validatedMailReplyTo()`** only; do not reimplement getenv validation elsewhere for this feature.
  - Keep email format checks in **`AuthService`** by routing the reset-request flow through logic that reuses the **existing private** `validateEmailFormat()` (e.g. a new public method on `AuthService` that calls it internally); do **not** add `EmailValidator` or similar.
  - **Mail:** Follow the same conventions as registration confirmation mail: env + recipient checks, 64-hex token regex before send, `rawurlencode` on the token in the URL, `@mail()`, `try/catch`, and distinct **`error_log`** markers (e.g. `camagru_password_reset_mail_*`); never throw to the client.
  - **Extend** `RegistrationConfirmationMailService` (or the project’s existing registration confirmation mail class) with **`trySendPasswordResetRequest(...)`** (or equivalent name)—**do not** create a second parallel mail service class for the same pattern.
  - **Do not** store or look up password reset state in **`confirmation_token`** or reuse confirmation-token repository methods for reset; reset uses **only** the dedicated DB columns and future #54 consume flow.
  - **Do not** add `TokenService`, generic “transactional mail” frameworks, or other speculative abstractions; any new helper should be **small and local** (e.g. private method in the mail class) only if it removes real duplication.
- **Acceptable new code (required for this feature)**
  - New DB columns: password reset token (e.g. hash) + expiry; migration/ALTER note for existing DBs.
  - **One** `UserRepository` method to set/replace those fields for a user id.
  - **One** public entry point on **`AuthService`** for “request password reset” orchestration.
  - **One** dedicated static method on the **existing** registration mail service for password-reset email body/send.
  - New **routes**, thin **`AuthController`** actions, and **views** for GET form + generic success (PRG or equivalent).
- **Layering**
  - Controllers thin (HTTP + view choice); business rules in **`AuthService`**; SQL in **`UserRepository`**; views HTML-only, no DB access.

Success Criteria  
- **S1:** `POST` with email for an existing **verified** user → DB holds a **new** reset token record (per spec: hash or stored secret + expiry); **one** mail attempt with a link whose path/query matches the agreed **phase 2** route and encodes the token; browser shows **generic success**.
- **S2:** Same `POST` for **unknown** email or **unverified** user → **no** mail attempt (or no send path taken); **no** destructive change to unrelated user data; **same** generic success as S1.
- **S3:** Invalid mail env or `mail()` failure → user still sees **generic success**; verified user row still has token+expiry persisted (optional: spec may allow skipping DB update when mail env missing—if so, document; default recommended: still save token so a later resend or ops could apply, or skip token if no mail to avoid orphan tokens—**pick one behavior in implementation plan and test it**).
- **S4:** Second `POST` for same user **replaces** prior reset token and expiry.

## Implementation Plan

1 add `password_reset_token` and `password_reset_expires_at` to `database/schema.sql` with ALTER comment for existing databases
2 add `UserRepository` prepared-statement method to set reset token hash + expiry for one user id (overwrites prior values)
3 register `GET` and `POST` for `/password-reset` in `src/routes/index.php` calling `AuthController`
4 add thin `AuthController` GET (form) and POST handlers: read POST, call `AuthService` reset-request entry point, always redirect or render generic success (no branching UX that leaks account existence)
5 add `AuthService` public method: trim email, reuse private `validateEmailFormat()`; if invalid return without DB/mail; else `UserRepository::findByEmail()`; if missing user or `email_verified !== 1` return; else `bin2hex(random_bytes(32))`, hash for DB, expiry, repository update, then `RegistrationConfirmationMailService::trySendPasswordResetRequest($email, $rawToken)`
6 extend `RegistrationConfirmationMailService` with `trySendPasswordResetRequest`: mirror registration mail pattern (`MailEnv`, recipient `filter_var`, 64-hex token regex, `rawurlencode`, headers incl. optional Reply-To, `@mail()`, try/catch, `error_log` markers e.g. `camagru_password_reset_mail_ok|_failed|_exception`); URL path `/password-reset/confirm?token=` for #54
7 add views for request form and identical generic success copy; optional link to login; update spec curl paths if route names differ

## Tests

**Test cases**

- **Success**
  - S1: `GET /password-reset` returns **200** and HTML form.
  - S2: `POST` with email of an **email-verified** user → same redirect/status as other anonymous outcomes; `password_reset_token` and `password_reset_expires_at` set in DB; `expires_at` strictly after “now” (SQLite compare).
  - S3: With valid `APP_BASE_URL` + `APP_MAIL_FROM`, PHP stderr shows **`camagru_password_reset_mail_ok`** (or `_failed` / `_exception` if transport fails—user path still success).
- **Failure / privacy**
  - F1: `POST` with **unknown** email → same HTTP outcome as S2; **no** new user rows; verified users’ reset columns unchanged.
  - F2: `POST` for **registered but unverified** user → same HTTP outcome as S2; **no** reset token written for that user.
  - F3: `POST` with **invalid** email format (e.g. `not-an-email`) → same HTTP outcome as S2; **no** reset token for any user.
- **Edge**
  - E1: Two `POST`s for the same verified user → second request **overwrites** token hash and expiry (values differ from first).
  - E2: `POST` with empty `email` → same generic success path; **no** token write (treated like invalid / no-op).

**Test Setup (authentication)**

```bash
# From repo root; PHP built-in server must see env (start server after export).
BASE=http://127.0.0.1:8080
DATABASE_PATH=database/camagru.db
export APP_BASE_URL="$BASE"
export APP_MAIL_FROM='camagru@example.com'
# optional: export APP_MAIL_REPLY_TO='support@example.com'

# Verified user UE (register then mark verified in DB)
UE="reset_req_$(date +%s)@example.com"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$UE" \
  -d "username=resetreq$(date +%s)" \
  -d "password=Password123!" \
  -d "confirm_password=Password123!"
sqlite3 "$DATABASE_PATH" "UPDATE users SET email_verified = 1, confirmation_token = NULL WHERE email = '$UE';"

# Unverified user UV (for F2)
UV="reset_unverified_$(date +%s)@example.com"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/register" \
  -d "email=$UV" \
  -d "username=resetunver$(date +%s)" \
  -d "password=Password123!" \
  -d "confirm_password=Password123!"
# leave email_verified = 0
```

**Execute tests**

```bash
# Run **Test Setup** first in the same shell so $UE and $UV are set.
BASE=http://127.0.0.1:8080
DATABASE_PATH=database/camagru.db

# S1: forgot-password form loads
curl -s -o /dev/null -w "%{http_code}\n" "$BASE/password-reset"

# S2 + S3: verified user — expect 302 or 200 per implementation; DB reset fields set
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/password-reset" -d "email=$UE"
sqlite3 "$DATABASE_PATH" "SELECT password_reset_token IS NOT NULL, password_reset_expires_at IS NOT NULL FROM users WHERE email = '$UE';"

# F1: unknown email — same status class; no side effects on UE row (token may exist from S2; re-run setup if you need clean UE)
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/password-reset" -d "email=nobody_$(date +%s)@example.com"

# F2: registered but unverified — no reset columns set
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/password-reset" -d "email=$UV"
sqlite3 "$DATABASE_PATH" "SELECT password_reset_token IS NULL OR password_reset_token = '' FROM users WHERE email = '$UV';"

# F3: invalid format
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/password-reset" -d "email=not-an-email"

# E1: second POST overwrites (capture hash before/after; should differ)
sqlite3 "$DATABASE_PATH" "SELECT password_reset_token FROM users WHERE email = '$UE';"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/password-reset" -d "email=$UE"
sqlite3 "$DATABASE_PATH" "SELECT password_reset_token FROM users WHERE email = '$UE';"

# E2: empty email
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/password-reset" -d "email="
```

Run **`php -S 127.0.0.1:8080 public/index.php`** from repo root after exports. Adjust paths if implementation uses something other than **`/password-reset`** (see Implementation Plan). Watch the server terminal for **`camagru_password_reset_mail_*`** lines.
