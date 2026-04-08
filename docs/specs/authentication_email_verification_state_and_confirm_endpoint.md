# Feature: Email verification — state, unverified registration, and confirmation endpoint

Tracks [GitHub #47](https://github.com/tomjoy75/camagru_php/issues/47) and `docs/feature_tree.md` (Authentication → email confirmation). Parent epic: [#6](https://github.com/tomjoy75/camagru_php/issues/6). **Out of scope for this spec:** sending confirmation email ([#48](https://github.com/tomjoy75/camagru_php/issues/48)), login blocking for unverified users ([#49](https://github.com/tomjoy75/camagru_php/issues/49)).

## Goal

Add **server-side email verification mechanics** without outbound mail and without changing the login outcome yet: extend the user model so new accounts are **unverified**, store a **single-use confirmation secret** (implementation may persist a random token and compare, or store a hash of the token—decide in implementation plan), expose a **public GET** route that accepts that token, and on success marks the user **verified**. Users can complete registration as today from an HTTP perspective (same success path as now); unverified users remain **able to log in** until [#49](https://github.com/tomjoy75/camagru_php/issues/49) is implemented.

## Behavior

- **Schema:** `users` gains columns needed for verification (e.g. boolean or integer **verified** flag default **unverified**, **confirmation token** or **token hash**, optional **`expires_at`** or fixed TTL policy). `database/schema.sql` and any project migration/bootstrap steps stay aligned.
- **Registration:** On successful account creation, the new row is **unverified** and a new confirmation secret is generated and stored according to the chosen representation. Registration **HTTP response** (redirect, flash, view) remains consistent with today unless the spec explicitly adds a short “check your email” message (optional copy only; **no** `mail()` in this issue).
- **Confirmation route:** A single public **GET** route (path chosen at implementation, e.g. `/register/confirm` or `/confirm-email`) reads the token from a query parameter (e.g. `token`).  
  - **Valid token** for an **unverified** user: mark user **verified**, **invalidate** the token (clear column(s) or replace so the same link cannot be reused—define in success criteria), redirect or render a small success page with escaped copy.  
  - **Invalid, missing, expired, or unknown token:** no change to any user row; respond with a **safe** generic or neutral message (no revelation that an email exists in the system beyond what the rest of the app already allows).  
  - **Already verified** user hits a still-valid token scenario: define idempotent behavior (e.g. success message without error, no duplicate work)—spell in success criteria.
- **MVC:** Router maps the new **GET**; **controller** reads valid UUID bytes or allowed charset for token param, calls **repository** (or thin service) for lookup and update; **views** only render HTML for confirmation result pages if used; **no** mail or session requirement for the confirmation GET.

## Constraints

- **MVC boundaries:** Controllers handle HTTP; repositories/services own persistence and token validation rules; views emit HTML only with `htmlspecialchars` for dynamic text.
- **PHP / data:** Standard library only; **prepared statements** for all SQL touching the new columns; no new Composer dependencies.
- **Security:** Use a **high-entropy** secret (length appropriate for a URL token); if storing a **hash**, compare in a **timing-safe** way; avoid logging full tokens in production; do not expose stack traces or SQL to the client.
- **Explicit non-goals:** No `mail()` or link emails ([#48](https://github.com/tomjoy75/camagru_php/issues/48)); no refusal of login for unverified users ([#49](https://github.com/tomjoy75/camagru_php/issues/49)); no password-reset flow.

## Success Criteria

- **S1:** After a successful registration, the new `users` row exists with **unverified** state per schema and a **non-empty** stored confirmation value (or equivalent), and password/login-related columns unchanged in meaning.
- **S2:** **GET** confirmation URL with the **correct** token for that user marks them **verified** and clears or rotates the confirmation secret so a second **GET** with the same token does not re-grant verification (or matches agreed idempotent rules for “already verified”).
- **S3:** **GET** with **wrong** / **malformed** / **missing** token results in **no** spurious verification of any user and **no** server error (**500**).
- **S4:** **GET** with an **expired** token (if expiry is implemented) does not verify the account.
- **F1:** Confirmation route rejects unsafe HTTP methods if mutation is **GET**-only elsewhere (e.g. **POST** to confirm → **404** or router-consistent safe rejection), with no unintended DB updates.
- **E1:** Behavior for “token valid but user already verified” is defined and tested (success or friendly message, no duplicate side effects).

## Implementation Plan

1. add `users` verification columns in `database/schema.sql` (and document or script any one-off update for existing dev DBs)
2. extend user creation in `UserRepository` (and call sites) so new rows are **unverified** with a generated confirmation secret persisted per chosen representation (plaintext token column vs hash)
3. add `UserRepository` read/update helpers: load pending user by confirmation token (and optional expiry rule); **verify + clear token** in one safe path
4. register public **`GET`** confirmation path in the router; ensure **`POST`** (or non-GET) to that path is rejected like other unsafe methods
5. implement confirmation **controller** action: read `token` query param, validate shape/length, call repository helpers, choose logical view + message for success / invalid / expired / already-verified
6. add minimal **view**(s) for confirmation outcomes (escaped copy only; optional shared layout)

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

**Test cases**

**Success**

- **S1:** After **POST** `/register` succeeds, the new `users` row is **unverified** and has a **non-empty** confirmation secret in the chosen column (assert via SQLite).
- **S2:** **GET** confirmation URL with the **correct** `token` returns a **non-500** response and the row becomes **verified** with confirmation secret **cleared** (or invalidated).
- **S3:** A **second** **GET** with the **same** `token` does **not** flip any other user to verified and leaves the already-verified user in a consistent state (token still empty / invalid link behavior per implementation).

**Failure**

- **F1:** **GET** confirmation URL with **missing** `token` → **non-500**, no broad verification side effects (spot-check DB unchanged for a control user if useful).
- **F2:** **GET** with **wrong** / **malformed** token → **non-500**, target user (if any) stays **unverified**.
- **F3:** **POST** to the confirmation path → **404** or router-consistent rejection, **no** verification state change from that request alone.

**Edge**

- **E1:** User already **verified**: hitting a still-valid or replayed token shows **idempotent** safe outcome (no error loop, no duplicate writes beyond no-op).
- **E2:** If **expiry** is implemented: **GET** with an **expired** token does not verify the account (**skip** this case if the first version has no expiry).

**Wire-up:** Set `BASE`, `DATABASE_PATH`, and **`CONFIRM`** to the final public confirmation **`GET`** path (example below uses `$BASE/register/confirm`). Set **`TOKEN_PARAM`** to the query key (default `token`). Set SQLite column names **`COL_VERIFIED`** / **`COL_TOKEN`** to match the implemented schema (defaults: `email_verified` with **0/1**, `confirmation_token` text). If only a **hash** is stored, the runnable block must still obtain the raw token used in the URL (e.g. the app writes the plaintext token to a column until confirm, or you derive it in a one-off dev step—document the chosen approach).

### Test Setup (registration only)

```bash
rm -f /tmp/camagru_confirm_test_email.txt
BASE=http://localhost:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
CONFIRM="$BASE/register/confirm"
TOKEN_PARAM=token
COL_VERIFIED=email_verified
COL_TOKEN=confirmation_token

STAMP=$(date +%s)
EMAIL="confirm_${STAMP}@example.com"
USER="confirmuser${STAMP}"
PASS='Testpass1!'

curl -s -o /dev/null -w "register HTTP %{http_code}\n" -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"

echo "$EMAIL" > /tmp/camagru_confirm_test_email.txt
echo "EMAIL=$EMAIL (also in /tmp/camagru_confirm_test_email.txt)"
```

### Execute tests

```bash
BASE=http://localhost:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
CONFIRM="$BASE/register/confirm"
TOKEN_PARAM=token
COL_VERIFIED=email_verified
COL_TOKEN=confirmation_token
EMAIL=${EMAIL:-$(cat /tmp/camagru_confirm_test_email.txt 2>/dev/null)}

user_row() {
  sqlite3 -separator '|' "$DATABASE_PATH" \
    "SELECT $COL_VERIFIED, COALESCE($COL_TOKEN,'') FROM users WHERE email='$EMAIL' LIMIT 1;"
}

TOKEN=$(sqlite3 "$DATABASE_PATH" "SELECT $COL_TOKEN FROM users WHERE email='$EMAIL' LIMIT 1;")

# F1 — missing token (expect non-500)
curl -s -o /dev/null -w "F1 missing token HTTP %{http_code}\n" "$CONFIRM"

# F2 — bogus token (expect non-500; S1 row should still be unverified if still unverified)
curl -s -o /dev/null -w "F2 bogus token HTTP %{http_code}\n" "$CONFIRM?$TOKEN_PARAM=not-a-real-token-xxxxxxxxxxxxxxxx"

# F3 — POST to confirm path (expect 404 or project-consistent non-success)
curl -s -o /dev/null -w "F3 POST confirm HTTP %{http_code}\n" -X POST "$CONFIRM?$TOKEN_PARAM=whatever"

# S1 / S2 / S3 / E1 — need registered user + token from DB
if [ -z "$EMAIL" ] || [ -z "$TOKEN" ]; then
  echo "SKIP S1–S3/E1: run Test Setup first; if TOKEN empty, check COL_TOKEN / registration path"
  exit 0
fi

echo "S1 before confirm (expect 0| and non-empty token): $(user_row)"

# S2 — valid token
curl -s -o /dev/null -w "S2 valid token HTTP %{http_code}\n" "$CONFIRM?$TOKEN_PARAM=$TOKEN"
echo "S2 after first confirm (expect 1| empty token): $(user_row)"

# S3 — replay same token (must not revert verified; token stays empty)
curl -s -o /dev/null -w "S3 replay token HTTP %{http_code}\n" "$CONFIRM?$TOKEN_PARAM=$TOKEN"
echo "S3 after replay (still verified, token empty): $(user_row)"

# E1 — already verified idempotent (same as S3; adjust expectations in manual review)

# E2 — optional: uncomment when expiry exists
# curl -s -o /dev/null -w "E2 expired token HTTP %{http_code}\n" "$CONFIRM?$TOKEN_PARAM=$EXPIRED_TOKEN"
```

**Manual:** Confirm success/error **HTML** copy is escaped and matches UX; if **302** redirects are used instead of **200**, follow `Location:` and re-check flashes as needed.
