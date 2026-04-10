# Feature: Notifications preference setting

Tracks [GitHub #35](https://github.com/tomjoy75/camagru_php/issues/35) and `docs/feature_tree.md` (Notifications → preference: boolean `notifications_enabled` per user, default on at registration, user-visible control). **Out of scope:** sending email (#37), hooking comment events (#36), in-app notification UI (#38), profile editing beyond this single preference (#12).

## Goal

Let every **logged-in** user **see** and **change** whether they want **comment-related notifications** enabled, stored in `users.notifications_enabled` (`INTEGER NOT NULL`, **1** = enabled, **0** = disabled). New accounts must continue to default to **enabled** without extra registration fields. This gives a stable flag for later issues (#36, #37) without implementing delivery yet.

## Behavior

- **Access:** Only users with a valid session may open the settings UI or submit updates. Guests are redirected to `/login` (or the same pattern as other auth-gated pages), with **no** change to any user row.
- **Read:** A dedicated **GET** route (e.g. `/settings/notifications`—final path chosen at implementation) renders a page showing the **current** value for the session user (loaded from the database), with clear copy that this controls future notification behavior (e.g. email when someone comments on their image).
- **Write:** A **POST** to the same path (or a dedicated **POST** path if the project prefers) updates **only** the row for `users.id` taken from the session. The client sends an explicit **on/off** signal (for example a checkbox paired with a hidden `0` default, or two radio values **`0`** / **`1`**—pick one pattern and use it consistently). The server maps that to **`notifications_enabled`** ∈ {0, 1}; any other submitted value is rejected without changing the row (safe error flash or inline error).
- **After a successful update:** Redirect **GET** back to the settings page (or the same URL with **302**) and show a short **success** flash (or equivalent), so the user sees the saved state.
- **Wrong HTTP method:** **GET** on the **POST-only** mutation route, if split, returns **404** (or router-consistent safe rejection); **POST** without auth does not update the database.
- **Registration:** `UserRepository::createUser` may omit `notifications_enabled` in the `INSERT` as today, relying on `database/schema.sql` **DEFAULT 1** so new users remain **enabled** until they change the preference.

## Constraints

- **MVC:** Router → controller handles session, validation, redirect, and flashes; **repository** (or thin service) runs the `UPDATE` / `SELECT` for the current user; **views** emit HTML only and escape dynamic text with `htmlspecialchars`.
- **PHP:** Standard library only; **prepared statements** for all SQL; no new dependencies.
- **Security:** The `user_id` used in `UPDATE` must come **only** from the server session, never from POST/GET. Do not expose stack traces, SQL, or internal paths to the client.
- **Data:** Respect `database/schema.sql`: `notifications_enabled INTEGER NOT NULL`; treat **NULL** in legacy rows as **1** only if the implementation chooses a read-time fallback—prefer keeping the column non-null in normal operation.

## Success Criteria

- **S1:** New user after registration has `notifications_enabled = 1` in the database (default), without filling any notification field on the register form.
- **S2:** Authenticated user opens the settings **GET** page and sees the same on/off state as stored in `users.notifications_enabled` for their id.
- **S3:** User turns notifications **off** via **POST** → row updates to **0**; subsequent **GET** shows **off**; SQLite (or app) reflects **0**.
- **S4:** User turns notifications **back on** via **POST** → row updates to **1**; subsequent **GET** shows **on**.
- **F1:** Unauthenticated **GET** or **POST** to the settings routes → no `notifications_enabled` change for any user; redirect to login (or equivalent).
- **F2:** Authenticated **POST** with an invalid enable/disable payload (not clearly **0** or **1**) → no row change; safe user-facing feedback, no **500**.
- **E1:** No other user’s `notifications_enabled` value changes when the session user updates their preference (spot-check second account in DB).

## Implementation Plan

1. register `GET` and `POST` `/settings/notifications` in the router and route to a controller; unauthenticated requests redirect to `/login` with no `UPDATE`
2. add `UserRepository` methods to read and update `notifications_enabled` for a given `user_id` via prepared statements
3. implement authenticated `GET`: load current flag from the repository and render a dedicated settings view with escaped copy and a form bound to that value
4. implement authenticated `POST`: map the submitted on/off field strictly to `0` or `1`, reject invalid input with an error flash and no `UPDATE`, otherwise `UPDATE`, set success flash, `302` to `GET` `/settings/notifications`
5. add a logged-in-only navigation link (e.g. in the shared layout/header) to `GET` `/settings/notifications`

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

**Test cases**

**Success**

- **S1:** After register + login, `users.notifications_enabled` for that email is **1** (schema default; no notification field on register).
- **S2:** Authenticated `GET /settings/notifications` → **200**; page shows current DB value (on vs off).
- **S3:** Authenticated `POST` with **off** (`notifications_enabled=0` or the project’s equivalent) → **302** back to settings; DB **0**; `GET` shows **off**.
- **S4:** Authenticated `POST` with **on** (`notifications_enabled=1`) → **302**; DB **1**; `GET` shows **on**.

**Failure**

- **F1:** Guest `GET` and guest `POST` → **302** to `/login` (or equivalent); no `UPDATE` to `users`.
- **F2:** Authenticated `POST` with missing or invalid notification field (not exactly **0** or **1** per implementation rules) → no **500**; DB value unchanged; safe feedback (flash or inline).

**Edge**

- **E1:** If mutation uses a separate `POST`-only URL, `GET` that URL → **404** (or router-safe rejection), no DB change.
- **E2:** Two authenticated `POST`s in a row (e.g. off then on) leave DB and `GET` consistent with the last successful submit.

**Wire-up:** Set `SETTINGS`, `NOTIFY_FIELD`, and `DATABASE_PATH` to match implementation. Default route: `GET` + `POST` `/settings/notifications`, form field `notifications_enabled` ∈ {`0`,`1`}.

### Test Setup (authentication)

```bash
rm -f cookies.txt
BASE=http://localhost:8080
SETTINGS="$BASE/settings/notifications"
NOTIFY_FIELD=notifications_enabled
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}

STAMP=$(date +%s)
EMAIL="notif_pref_${STAMP}@example.com"
USER="notifuser${STAMP}"
PASS='Testpass1!'

curl -s -o /dev/null -c cookies.txt -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"
curl -s -o /dev/null -c cookies.txt -b cookies.txt -L -X POST "$BASE/login" \
  -d "username=$USER" -d "password=$PASS"

# Persist email for a later Execute tests run (separate shell)
echo "$EMAIL" > /tmp/camagru_notif_test_email.txt
```

### Execute tests

```bash
BASE=http://localhost:8080
SETTINGS="$BASE/settings/notifications"
NOTIFY_FIELD=notifications_enabled
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
EMAIL=${EMAIL:-$(cat /tmp/camagru_notif_test_email.txt 2>/dev/null)}

notif_db() {
  sqlite3 "$DATABASE_PATH" "SELECT notifications_enabled FROM users WHERE email='$EMAIL' LIMIT 1;"
}

# F1 — guest GET / POST (no cookie): expect redirect to login, not 200 settings
curl -s -o /dev/null -D /tmp/np_guest_get.txt -w "F1 guest GET -> %{http_code}\n" "$SETTINGS"
grep -i '^location:' /tmp/np_guest_get.txt || true
curl -s -o /dev/null -D /tmp/np_guest_post.txt -w "F1 guest POST -> %{http_code}\n" -X POST "$SETTINGS" -d "${NOTIFY_FIELD}=0"
grep -i '^location:' /tmp/np_guest_post.txt || true

# S1 — default enabled in DB
if [ -n "$EMAIL" ] && [ -f "$DATABASE_PATH" ]; then
  echo "S1 DB notifications_enabled (expect 1): $(notif_db)"
else
  echo "SKIP S1: run Test Setup (email file + DB path)"
fi

# S2 — authenticated GET settings
if [ -s cookies.txt ]; then
  curl -s -o /dev/null -w "S2 GET settings HTTP %{http_code}\n" -b cookies.txt "$SETTINGS"
else
  echo "SKIP S2: no cookies.txt (run Test Setup)"
fi

if [ ! -s cookies.txt ] || [ -z "$EMAIL" ]; then
  echo "SKIP S3/S4/F2/E2: run Test Setup for cookies.txt and /tmp/camagru_notif_test_email.txt"
  exit 0
fi

# S3 — turn off
curl -s -o /dev/null -D /tmp/np_off.txt -b cookies.txt -c cookies.txt -X POST "$SETTINGS" -d "${NOTIFY_FIELD}=0"
echo "S3 POST off -> $(grep -i '^HTTP' /tmp/np_off.txt | head -1)$(grep -i '^location:' /tmp/np_off.txt | head -1)"
echo "S3 DB after off (expect 0): $(notif_db)"

# S4 — turn on
curl -s -o /dev/null -D /tmp/np_on.txt -b cookies.txt -c cookies.txt -X POST "$SETTINGS" -d "${NOTIFY_FIELD}=1"
echo "S4 POST on -> $(grep -i '^HTTP' /tmp/np_on.txt | head -1)$(grep -i '^location:' /tmp/np_on.txt | head -1)"
echo "S4 DB after on (expect 1): $(notif_db)"

# F2 — invalid payload (expect no 500; DB still 1 from S4)
before=$(notif_db)
curl -s -o /dev/null -w "F2 invalid POST HTTP %{http_code}\n" -b cookies.txt -X POST "$SETTINGS" -d "${NOTIFY_FIELD}=bogus"
after=$(notif_db)
echo "F2 DB unchanged (expect $before == $after): before=$before after=$after"

# E2 — off then on again
curl -s -o /dev/null -b cookies.txt -c cookies.txt -X POST "$SETTINGS" -d "${NOTIFY_FIELD}=0"
curl -s -o /dev/null -b cookies.txt -c cookies.txt -X POST "$SETTINGS" -d "${NOTIFY_FIELD}=1"
echo "E2 DB after toggle pair (expect 1): $(notif_db)"

# E1 — only if POST is on a different path than GET; example placeholder (uncomment and set POST_ONLY if applicable):
# POST_ONLY="$BASE/settings/notifications/update"
# curl -s -o /dev/null -w "E1 GET POST-only URL -> %{http_code}\n" -b cookies.txt "$POST_ONLY"
```

**Manual / cross-user (E1 from success criteria):** Create a second user, note their `notifications_enabled`, run S3/S4 as the first user, confirm the second user’s column unchanged in SQLite.
