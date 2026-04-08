# Feature: Send confirmation email with activation link

Tracks [GitHub #48](https://github.com/tomjoy75/camagru_php/issues/48) and `docs/feature_tree.md` (Authentication → email confirmation). **Depends on:** [#47](https://github.com/tomjoy75/camagru_php/issues/47) — verification state, unverified registration, and public `GET /register/confirm?token=…`. Parent epic: [#6](https://github.com/tomjoy75/camagru_php/issues/6). **Out of scope:** changing token generation or storage, changing confirmation endpoint behavior, login blocking for unverified users ([#49](https://github.com/tomjoy75/camagru_php/issues/49)), password reset, resend-confirmation flow (unless explicitly added later).

## Goal

After a **successful** registration—when the user row already exists as **unverified** and a confirmation **token** is stored—send **one** plain-text email to the new user’s **`users.email`** containing an **absolute HTTPS or HTTP URL** to the **existing** confirmation route (same path and `token` query parameter as #47). Registration must **complete successfully** (same HTTP outcome as today: e.g. redirect to login) **even if** email delivery fails or is skipped due to missing/invalid mail environment configuration; the user remains **unverified** until they open the link in the message or an admin fixes data.

## Behavior

- **Trigger:** On the **successful** registration path only—after `INSERT` (or equivalent) has committed for the new user with `email_verified = 0` and a non-empty confirmation token—attempt **at most one** `mail()` (or project-standard PHP mail call) per registration.
- **Link:** Build `APP_BASE_URL` (trimmed, no trailing slash) + canonical confirmation path + `?token=` + **URL-encoded** token (64 hex per #47). The link must match what `GET /register/confirm` expects. **Do not** log full URLs containing the raw token in production logs if avoidable; **do** follow the same env validation style as comment notification email where sensible.
- **Message:** **Plain text** is sufficient: short explanation, the link on its own line, optional one-line branding; **no** requirement for HTML. **Do not** include the password. **Do not** change the confirmation token in the database when sending fails.
- **Configuration:** Reuse **env-based** configuration **consistent with** `CommentNotificationService` / [#37](https://github.com/tomjoy75/camagru_php/issues/37): at minimum **`APP_BASE_URL`** and **`APP_MAIL_FROM`** validated before send; optional **`APP_MAIL_REPLY_TO`** if already used project-wide. If `APP_BASE_URL` or `APP_MAIL_FROM` is missing/invalid, **skip** sending (no uncaught exception); registration still succeeds.
- **Failure isolation:** If `mail()` returns **false** or throws, catch/log if the project already uses `error_log` for mail—**do not** surface internal errors to the registrant; **do not** roll back the user row; **do not** change the HTTP redirect/response of registration compared to the pre-#48 success path except for an **optional** non-blocking flash (“Account created; if configured, a confirmation email was sent”)—only if the product owner wants it; default may stay silent on mail outcome.
- **MVC:** Keep HTTP and registration orchestration in **controller** / existing **AuthService** path; encapsulate **compose + send** in a small dedicated class or align with existing mail helper patterns—**no** HTML generation for the whole site inside the mailer beyond string assembly.

## Constraints

- **PHP:** Standard library only; **`mail()`** for delivery unless the repo already standardizes otherwise for [#37].
- **Security:** Recipient address from **trusted server-side data** (the email just stored for that user); validate with `FILTER_VALIDATE_EMAIL` before send; never accept recipient from client input for this mail.
- **MVC / layering:** Repositories remain SQL-centric; do not query the DB from a view; mail body assembly can live in a service dedicated to transactional mail.
- **Explicit non-goals:** Revising #47 routes or token column semantics; **#49** login gate; **#13** password-reset mail; queue/async systems; third-party APIs.

## Success Criteria

- **S1:** With **valid** env (`APP_BASE_URL`, `APP_MAIL_FROM`) and a working local transport (or capture such as Mailpit), a **new** registration results in **one** outbound message whose body contains a **single** absolute link that successfully loads the confirmation page when opened (token still valid).
- **S2:** With env **missing** or **invalid** for send, registration still returns **success** (e.g. **302** to `/login` as today); user row remains **unverified** with token unchanged; **no** **500** from registration.
- **S3:** Malformed recipient (should not happen if registration validated email) or `mail()` **false**—registration HTTP success **unchanged**; no leaked stack traces to the client.
- **F1:** No **double-send** for a single successful registration request (single logical attempt per submit).
- **E1:** Link uses the same **origin** as `APP_BASE_URL` and the same **path** as the live **GET** confirm route from #47.

## Implementation Plan

1. add a small mail service (or reuse extracted env validators) with `APP_BASE_URL` / `APP_MAIL_FROM` (optional `APP_MAIL_REPLY_TO`) validation aligned with `CommentNotificationService`
2. implement one method: build plaintext subject + body with a single absolute `/register/confirm?token=` URL (`rawurlencode`); call `mail()`; skip without throw when env or recipient email is invalid
3. wrap send in `try/catch`; on `mail()` false or exception, swallow and optional `error_log` line with non-PII marker (match #37 style)
4. after successful `createUser` in `AuthService::register`, invoke that method once with registrant email + confirmation token
5. confirm `AuthController::register` success path stays the same (302 to `/login`, no flash tied to mail result unless product explicitly adds optional copy)
6. manually or via later `## Tests`: one registration yields at most one `mail()` attempt on the happy path

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

**Test cases**

**Success**

- **S1:** With valid **`APP_BASE_URL`** and **`APP_MAIL_FROM`** on the PHP process, **`POST /register`** (valid payload) → **302** (or same success code as pre-#48); new row **unverified** with non-empty **`confirmation_token`**; **one** `mail()` attempt occurred (confirm via local capture, MTA, or implementation **`error_log`** marker such as `camagru_confirm_mail_ok` — no PII in log); message body includes an absolute URL that returns a **non-500** confirmation response when requested with **`GET`** (same `token` as in DB).
- **S2:** Same registration shape **with** valid env: after register, **`GET "${APP_BASE_URL}/register/confirm?token=$(sqlite …)`** → **200** (or project-consistent success), and DB still shows **verified** only **after** that GET (optional follow-up check).

**Failure**

- **F1:** **`POST /register`** with valid payload **without** usable mail env (missing or invalid **`APP_BASE_URL`** or **`APP_MAIL_FROM`**) → **302** (or identical success to #47); **no** **500**; user row exists **unverified** with **unchanged** token semantics (token still present for confirm link).
- **F2:** Simulated **`mail()`** **false** or exception (dev-only hook if you add one) → registration HTTP success **unchanged**; **no** stack trace in browser response.

**Edge**

- **E1:** Confirm URL in the composed message matches **`APP_BASE_URL`** (origin, no double slash) and path **`/register/confirm`**, with **`token`** query matching the stored secret (**`rawurlencode`**-safe).
- **E2:** Two successive valid registration submits for **different** emails each trigger **at most one** send attempt **each** (no burst from a single button double-click beyond duplicate rows — product may allow duplicate email error on second).

**Wire-up:** `BASE` = public site URL (match **`APP_BASE_URL`**). `DATABASE_PATH` → project `database/camagru.db`. Start PHP **from repo root** with env visible to the server process, e.g. `cd public && php -S 127.0.0.1:8080 index.php` (or your documented router). Implementers: emit **`camagru_confirm_mail_ok` / `camagru_confirm_mail_failed` / `camagru_confirm_mail_exception`** (ids or no PII only) if you mirror #37.

### Test Setup (mail env on)

```bash
export APP_BASE_URL=http://localhost:8080
export APP_MAIL_FROM=camagru@example.com
# optional: export APP_MAIL_REPLY_TO=support@example.com
rm -f /tmp/camagru_reg_mail_test_email.txt
BASE=http://localhost:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
COL_VERIFIED=email_verified
COL_TOKEN=confirmation_token

STAMP=$(date +%s)
EMAIL="regmail_${STAMP}@example.com"
USER="regmailuser${STAMP}"
PASS='Testpass1!'

curl -s -o /dev/null -D /tmp/reg_mail_headers.txt -w "register HTTP %{http_code}\n" -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"

echo "$EMAIL" > /tmp/camagru_reg_mail_test_email.txt
TOKEN=$(sqlite3 "$DATABASE_PATH" "SELECT $COL_TOKEN FROM users WHERE email='$EMAIL' LIMIT 1;")
echo "EMAIL=$EMAIL TOKEN_LEN=${#TOKEN}"
```

### Execute tests (happy path + link)

```bash
BASE=http://localhost:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
COL_VERIFIED=email_verified
COL_TOKEN=confirmation_token
EMAIL=${EMAIL:-$(cat /tmp/camagru_reg_mail_test_email.txt 2>/dev/null)}
APP_BASE_URL=${APP_BASE_URL:-http://localhost:8080}

# S1 / F1 baseline: registration must not 500
test -n "$EMAIL" || { echo "SKIP: run Test Setup first"; exit 0; }
TOKEN=$(sqlite3 "$DATABASE_PATH" "SELECT $COL_TOKEN FROM users WHERE email='$EMAIL' LIMIT 1;")
echo "S1 row: $(sqlite3 -separator '|' "$DATABASE_PATH" "SELECT $COL_VERIFIED, length(COALESCE($COL_TOKEN,'')) FROM users WHERE email='$EMAIL' LIMIT 1;")"

# Link smoke (body should contain this URL pattern)
CONFIRM_URL="${APP_BASE_URL}/register/confirm?token=${TOKEN}"
curl -s -o /dev/null -w "GET confirm URL HTTP %{http_code}\n" "$CONFIRM_URL"

# Optional: stderr / server log — adjust after implementation
# grep camagru_confirm_mail /path/to/php-server.log

echo "Manual S1: confirm one mail received or camagru_confirm_mail_ok in logs; F1/E1: body line equals link above (encoded token)."
```

### Execute tests (no mail env — skip send)

```bash
# Run in a shell where APP_BASE_URL and APP_MAIL_FROM are UNSET for the next php -S session,
# or export empty/invalid values, then repeat register with a new email only:

BASE=http://localhost:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
STAMP=$(date +%s)
EMAIL="regmail_skip_${STAMP}@example.com"
USER="regmailskip${STAMP}"
PASS='Testpass1!'

curl -s -o /dev/null -w "register without mail env HTTP %{http_code}\n" -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"

sqlite3 -separator '|' "$DATABASE_PATH" \
  "SELECT email_verified, length(COALESCE(confirmation_token,'')) FROM users WHERE email='$EMAIL' LIMIT 1;"
# Expect 302 (or success), 0|<positive token length>; no 500
```

**Manual:** Inbox or **Mailpit** shows plain-text, single link line, **no** password; **`From`** matches **`APP_MAIL_FROM`**.
