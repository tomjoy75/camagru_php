# Feature: Login with username and password (subject V.2)

Tracks [GitHub #55](https://github.com/tomjoy75/camagru_php/issues/55) and `docs/feature_tree.md` (Authentication → Login). **Replaces** email-as-identifier on **POST /login** only; registration, password reset, profile email flows, and mail continue to use **email** where they already do.

## Goal

Align the **sign-in** path with the subject: the user connects using **username** and **password**. The application must authenticate by **username** (trimmed), verify the password, and apply the **same** post-credential rules as today (notably **email verification** before a session is created).

## Behavior

- **GET /login:** Login form shows a **username** field (label, `id`/`name`, and appropriate input type—typically `text`, not `email`) and **password**; optional links (e.g. forgot password) unchanged.
- **POST /login:** Controller reads **username** and **password** from POST (field name aligned with the form, e.g. `username`). **AuthService::login** accepts a **username** string: trim; if empty after trim, return a **required** style error (equivalent to today’s empty-email handling, but wording for username).
- **Lookup:** Load the user row with **`UserRepository::findByUsername`** (prepared statement, already present). If no row or **`password_verify`** fails, return the **same class** of outcome as today: **one generic invalid-credentials message** (copy updated to **username**, e.g. “Invalid username or password.”), **no** session, **no** hint whether the username exists.
- **Verified email gate:** Unchanged: if password verifies but **`email_verified` ≠ 1**, reject login with the **existing** verification-required error key/copy; **no** session.
- **Success:** Unchanged: set **`$_SESSION['user_id']`**, redirect as today (e.g. `/`).
- **Out of scope:** Changing **registration** to drop email; changing **password reset** to username; adding “login with email” as a second option; new routes beyond **`/login`**.

## Constraints

- **MVC:** **AuthService** owns credential validation and verification rule; **AuthController** only passes POST fields and handles redirect vs re-render; **views** output HTML only and **escape** dynamic values with **`htmlspecialchars`**.
- **PHP / data:** Standard library only; **prepared statements** for username lookup; **`password_verify`** unchanged; no new dependencies.
- **Security:** Do **not** increase account enumeration beyond the current login flow: generic failure for unknown username or wrong password; verification message only when password **succeeded** and email is unverified (same as **`authentication_login_requires_email_verification.md`**).
- **Docs / specs:** Update any **user-facing** strings or **curl** examples that still assume **email** on **POST /login** (including README and feature specs that document login setup).

## Success Criteria

- **S1:** A **verified** user can **POST /login** with **correct username + password** and receives an authenticated session and the same success redirect as before.
- **S2:** Wrong password or **unknown** username yields **one** generic invalid-credentials response; **no** session; **no** verification-specific wording.
- **S3:** **Correct** password but **unverified** email still blocks login with the **verification-required** message; **no** session.
- **S4:** **Empty** username (after trim) is rejected with a clear **required** error without creating a session.
- **S5:** No **500** on normal login paths for the cases above; password reset and registration flows still work and still use **email** where specified.

## Implementation Plan

1 replace the login form email field in `login.php` with a username field (`name`/`id`/`label`, `type="text"`), bind repopulation to `$username` with `htmlspecialchars`
2 set `$username = ''` in `AuthController::showLoginForm` (drop `$email` for this view)
3 in `AuthController::login` read `$_POST['username']` and `password`, call `AuthService::login`; on error re-render with `$username` for repopulation
4 in `AuthService::login` switch to trimmed username: required-empty error copy, `UserRepository::findByUsername`, generic invalid-credentials message mentioning username, keep `password_verify` and `email_verified` logic unchanged
5 update `docs/` specs and `README.md` where runnable login examples still POST `email` to `/login`; use `username` and align any narrative that describes email-based sign-in

## Tests

**Test cases**

**Success**

- **S1:** After **GET** `/register/confirm?token=…` for the test user, **POST** `/login` with **correct** `username` + `password` → **302** with `Location: /` (or equivalent) and `Set-Cookie` for the session; follow-up **GET** `/` with `-b cookies.txt` shows an authenticated session (same barometer as today, e.g. logout link).

**Failure**

- **F1:** **POST** `/login` with a **known** username and **wrong** password → **200**; body contains the **generic** invalid-credentials message (substring must **not** mention email); body **must not** contain the verification-only phrase.
- **F2:** **POST** `/login` with a **non-existent** username and any password → **200**; same generic invalid-credentials message only; **no** verification-only phrase.
- **F3:** **POST** `/login` with **empty** `username` (omit or `username=`) and any password → **200**; **required-username** style error; **no** session (no `Set-Cookie` session establishing login success—i.e. no redirect to `/` as success).

**Edge**

- **E1:** While the account is still **unverified**, **POST** `/login` with **correct** `username` + `password` → **200**; body includes the **verification-required** message; **no** redirect to `/`.
- **E2:** **POST** `/login` with username surrounded by **ASCII spaces** (same registered username, padded) + correct password **after** verification → same success as **S1** (trim behavior).

**Wire-up:** From repo root: `php -S 127.0.0.1:8080 public/index.php`. Align `BASE` and `DATABASE_PATH` with your environment. After implementation, set `GENERIC_PHRASE`, `VERIFY_PHRASE`, and `REQUIRED_PHRASE` to substrings of the final strings in `AuthService` / `login.php`.

### Test Setup (registration + token)

```bash
rm -f cookies.txt
BASE=http://127.0.0.1:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}

STAMP=$(date +%s)
EMAIL="login55_${STAMP}@example.com"
USER="u55_${STAMP}"
PASS='Testpass1!'

curl -s -o /dev/null -w "setup register HTTP %{http_code}\n" -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"

echo "$EMAIL" > /tmp/camagru_login55_email.txt
echo "$USER" > /tmp/camagru_login55_user.txt
TOKEN=$(sqlite3 "$DATABASE_PATH" "SELECT confirmation_token FROM users WHERE email='$EMAIL' LIMIT 1;")
echo "USER=$USER EMAIL=$EMAIL TOKEN_LEN=${#TOKEN}"
```

### Execute tests

```bash
BASE=http://127.0.0.1:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
USER=${USER:-$(cat /tmp/camagru_login55_user.txt 2>/dev/null)}
PASS='Testpass1!'
GENERIC_PHRASE='Invalid username or password'
VERIFY_PHRASE='verify your email'
REQUIRED_PHRASE='Username is required'
TOKEN=$(sqlite3 "$DATABASE_PATH" "SELECT confirmation_token FROM users WHERE username='$USER' LIMIT 1;")

[ -n "$USER" ] || { echo 'Run Test Setup first'; exit 1; }

# E1 — unverified + correct password: 200, verification phrase, no redirect to /
curl -sS -D /tmp/cam55_u.headers -o /tmp/cam55_u.body -w "E1 HTTP %{http_code}\n" -X POST "$BASE/login" \
  -d "username=$USER" -d "password=$PASS"
grep -qi '^Location:[[:space:]]*/' /tmp/cam55_u.headers && echo 'E1 FAIL: unexpected redirect' || echo 'E1 OK: no redirect to /'
grep -q "$VERIFY_PHRASE" /tmp/cam55_u.body && echo 'E1 OK: verification phrase' || echo 'E1 CHECK: VERIFY_PHRASE'

# F1 — wrong password
curl -sS -o /tmp/cam55_wrong.body -w "F1 HTTP %{http_code}\n" -X POST "$BASE/login" \
  -d "username=$USER" -d "password=Wrongpass999!"
grep -q "$GENERIC_PHRASE" /tmp/cam55_wrong.body && echo 'F1 OK: generic error'
grep -q "$VERIFY_PHRASE" /tmp/cam55_wrong.body && echo 'F1 FAIL: verification leaked' || true

# F2 — unknown username
curl -sS -o /tmp/cam55_unknown.body -w "F2 HTTP %{http_code}\n" -X POST "$BASE/login" \
  -d "username=nouser_${RANDOM}_x" -d "password=$PASS"
grep -q "$GENERIC_PHRASE" /tmp/cam55_unknown.body && echo 'F2 OK'

# F3 — empty username
curl -sS -D /tmp/cam55_empty.headers -o /tmp/cam55_empty.body -w "F3 HTTP %{http_code}\n" -X POST "$BASE/login" \
  -d "username=" -d "password=$PASS"
grep -qi '^Location:[[:space:]]*/' /tmp/cam55_empty.headers && echo 'F3 FAIL: redirect' || echo 'F3 OK: no success redirect'
grep -q "$REQUIRED_PHRASE" /tmp/cam55_empty.body && echo 'F3 OK: required phrase' || echo 'F3 CHECK: REQUIRED_PHRASE'

# S1 / E2 — confirm, then login with username (and padded username)
if [ ${#TOKEN} -ne 64 ]; then echo 'SKIP S1/E2: token'; else
  curl -sS -o /dev/null -w "confirm HTTP %{http_code}\n" "$BASE/register/confirm?token=$TOKEN"
  rm -f cookies.txt
  curl -sS -D /tmp/cam55_ok.headers -o /dev/null -c cookies.txt -w "S1 HTTP %{http_code}\n" -X POST "$BASE/login" \
    -d "username=$USER" -d "password=$PASS"
  grep -qi '^Location:[[:space:]]*/' /tmp/cam55_ok.headers && echo 'S1 OK: redirect' || echo 'S1 FAIL'
  curl -sS -D /tmp/cam55_pad.headers -o /dev/null -c /tmp/cam55_pad_cookies.txt -w "E2 HTTP %{http_code}\n" -X POST "$BASE/login" \
    -d "username=  ${USER}  " -d "password=$PASS"
  grep -qi '^Location:[[:space:]]*/' /tmp/cam55_pad.headers && echo 'E2 OK: trim login' || echo 'E2 FAIL'
fi
```

**Manual:** Adjust phrase greps to final copy. Confirm **S5** by spot-checking **POST** `/password-reset` and **POST** `/register` still accept **email** as today. If **GET** `/` auth detection differs, replace the optional home check with your layout’s authenticated marker.
