# Feature: Login requires verified email

Tracks [GitHub #49](https://github.com/tomjoy75/camagru_php/issues/49) and `docs/feature_tree.md` (Authentication → Login / email confirmation). **Depends on:** [#47](https://github.com/tomjoy75/camagru_php/issues/47) — `email_verified`, `confirmation_token`, and public confirmation `GET`. Parent epic: [#6](https://github.com/tomjoy75/camagru_php/issues/6). **Out of scope:** resend-confirmation UI or rate limiting (optional follow-up), password reset, changing registration or confirmation endpoint behavior.

## Goal

Ensure **unverified** accounts ( `email_verified = 0` after #47) **cannot** open an authenticated session: after **correct** username and password, login **succeeds only** if the user is **verified**. This closes the gap explicitly deferred from #47/#48.

## Behavior

- **Credential check unchanged up to password:** Username required; user lookup by username (`findByUsername`); `password_verify` as today. Wrong or missing credentials → same outcome as now (e.g. single generic **invalid username or password** message), **no** session.
- **After password verifies:** If `email_verified` is **not** verified (implementation: **0** or falsey per schema), **reject** login: **no** `$_SESSION['user_id']`; re-render login view with a **clear, user-visible** error (e.g. that they must **confirm their email** first and may use the link from the confirmation message). This message is shown **only** when the password was correct but verification is pending—so it does not replace the generic failure for bad credentials.
- **Verified users:** Same success path as today: set session user id, redirect to home (or existing post-login target).
- **Data source:** Use the **same** user row already returned for login (e.g. `findByUsername` including `email_verified`); no extra roundtrip unless the codebase already separates concerns and requires one.
- **MVC:** **AuthService** (or equivalent) owns the rule “verified required”; **AuthController** stays thin (call service, branch on errors vs user).

## Constraints

- **MVC boundaries:** No business rules in views; escape all dynamic copy in the login template with `htmlspecialchars`.
- **PHP / data:** Standard library only; **prepared statements** unchanged for lookups; no new dependencies.
- **Security:** Do not expose whether an email exists in the system **more** than the current login flow already does; the new message appears **only** when password verification has **succeeded** (same disclosure class as “you got the password right”).
- **Explicit non-goals:** **Resend confirmation** email button, token rotation, or new routes—unless added in a follow-up issue; changing the **registration** redirect or **GET /register/confirm** semantics.

## Success Criteria

- **S1:** A user row with **correct password** and `email_verified = 0` **never** receives an authenticated session from **POST /login**; login view shows the **verification-required** error (or agreed copy).
- **S2:** After the same user completes **GET /register/confirm** successfully (`email_verified = 1`), **POST /login** with the same credentials **creates** a session and redirects like a normal login.
- **S3:** Wrong password or unknown username still yields the **existing** generic invalid-credentials behavior, with **no** verification-specific wording.
- **S4:** No **500** responses on the normal login path for verified / unverified / invalid credential cases.

## Implementation Plan

1 keep AuthService::login username lookup and password_verify; wrong credentials return the existing generic form error and null user
2 after password_verify succeeds, if email_verified is unverified return non-empty errors with a dedicated key and null user
3 render that dedicated error in login.php with htmlspecialchars when set
4 AuthController::login sets session and redirects only when service errors are empty and user is non-null

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

**Test cases**

**Success**

- **S1:** **POST** `/login` with **correct** password while the user row has `email_verified = 0` → **200** HTML (login page); response **must not** advertise a redirect to `/` as the login outcome; body includes the **verification-required** message (match final copy).
- **S2:** After **GET** `/register/confirm?token=…` succeeds for that user (`email_verified = 1`), **POST** `/login` with the same **username** + password → **302** with `Location: /` (or equivalent success redirect) and a session cookie as today.

**Failure**

- **F1:** **POST** `/login` with **wrong password** for an existing account → **200**; body contains the existing generic **Invalid username or password** text; body **does not** contain the verification-only message.
- **F2:** **POST** `/login` with an **unknown** username → **200**; generic invalid-credentials copy only; **no** verification-only message.

**Edge**

- **E1:** **Verified** user login behavior matches pre-#49 success (**302**, session).
- **E2:** Unverified correct-password login returns **non-500**.

**Wire-up:** Start PHP from repo root (`php -S 127.0.0.1:8080 public/index.php`). Set `BASE` to match. Set `DATABASE_PATH` to `database/camagru.db` (or your SQLite path). Set `VERIFY_PHRASE` to a **substring** of the final verification error string in `login.php` after implementation.

### Test Setup (registration + unverified user)

```bash
rm -f cookies.txt
BASE=http://127.0.0.1:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
VERIFY_PHRASE='verify your email'

STAMP=$(date +%s)
EMAIL="login49_${STAMP}@example.com"
USER="u49_${STAMP}"
PASS='Testpass1!'

curl -s -o /dev/null -w "setup register HTTP %{http_code}\n" -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"

echo "$EMAIL" > /tmp/camagru_login49_email.txt
TOKEN=$(sqlite3 "$DATABASE_PATH" "SELECT confirmation_token FROM users WHERE email='$EMAIL' LIMIT 1;")
echo "EMAIL=$EMAIL (saved /tmp/camagru_login49_email.txt) TOKEN_LEN=${#TOKEN}"
sqlite3 "$DATABASE_PATH" "SELECT email, email_verified FROM users WHERE email='$EMAIL';"
```

### Execute tests

```bash
BASE=http://127.0.0.1:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
VERIFY_PHRASE='verify your email'
EMAIL=${EMAIL:-$(cat /tmp/camagru_login49_email.txt 2>/dev/null)}
PASS='Testpass1!'
[ -n "$EMAIL" ] || { echo 'Run Test Setup first'; exit 1; }
USER=$(sqlite3 "$DATABASE_PATH" "SELECT username FROM users WHERE email='$EMAIL' LIMIT 1;")
TOKEN=$(sqlite3 "$DATABASE_PATH" "SELECT confirmation_token FROM users WHERE email='$EMAIL' LIMIT 1;")

# S1 / E2 — unverified + correct password: 200, no Location: /, body mentions verification
curl -sS -D /tmp/cam49_login.headers -o /tmp/cam49_login.body -w "S1 HTTP %{http_code}\n" -X POST "$BASE/login" \
  -d "username=$USER" -d "password=$PASS"
if grep -qi '^Location:[[:space:]]*/' /tmp/cam49_login.headers; then echo 'S1 FAIL: unexpected redirect to /'; else echo 'S1 OK: no redirect to /'; fi
grep -q "$VERIFY_PHRASE" /tmp/cam49_login.body && echo 'S1 OK: verification phrase in body' || echo 'S1 CHECK: adjust VERIFY_PHRASE to match login copy'

# F1 — wrong password: generic error, no verification phrase
curl -sS -o /tmp/cam49_wrong.body -w "F1 HTTP %{http_code}\n" -X POST "$BASE/login" \
  -d "username=$USER" -d "password=Wrongpass999!"
grep -q 'Invalid username or password' /tmp/cam49_wrong.body && echo 'F1 OK'
grep -q "$VERIFY_PHRASE" /tmp/cam49_wrong.body && echo 'F1 FAIL: verification phrase leaked' || echo 'F1 OK: no verification phrase'

# F2 — unknown username
curl -sS -o /tmp/cam49_unknown.body -w "F2 HTTP %{http_code}\n" -X POST "$BASE/login" \
  -d "username=nouser_${RANDOM}_x" -d "password=$PASS"
grep -q 'Invalid username or password' /tmp/cam49_unknown.body && echo 'F2 OK'

# S2 / E1 — confirm then login with cookie jar
if [ ${#TOKEN} -ne 64 ]; then echo 'SKIP S2: missing 64-char token'; else
  curl -sS -o /dev/null -w "confirm HTTP %{http_code}\n" "$BASE/register/confirm?token=$TOKEN"
  rm -f cookies.txt
  curl -sS -D /tmp/cam49_ok.headers -o /dev/null -c cookies.txt -w "login verified HTTP %{http_code}\n" -X POST "$BASE/login" \
    -d "username=$USER" -d "password=$PASS"
  grep -qi '^Location:[[:space:]]*/' /tmp/cam49_ok.headers && echo 'S2 OK: redirect after verified login' || echo 'S2 FAIL: expected redirect'
  curl -sS -o /tmp/cam49_home.body -b cookies.txt -w "GET / HTTP %{http_code}\n" "$BASE/"
  grep -qi 'logout\|log out' /tmp/cam49_home.body && echo 'E1 OK: session works on /' || echo 'E1 CHECK: home HTML may differ; confirm auth manually'
fi
```

**Manual:** Adjust `VERIFY_PHRASE` to the exact substring you ship in `login.php`. If the app uses different home-page markers, replace the `logout` grep in **E1** with a check your layout guarantees for authenticated users.
