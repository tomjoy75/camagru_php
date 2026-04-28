# Feature: Persist last compose parameters on saved images

## Goal

Store the **last successful server-side compose** metadata on each row in `images` when the user saves, so saved gallery files can be correlated with sticker choice and pose without inferring from pixels. Supports debugging, future UI subtitles, and optional analytics without requiring full compose history in this issue.

## Behavior

1. **Database (`images`)**  
   Extend the table with nullable columns (exact nullability decided at implementation, but existing rows must remain valid):

   - `last_sticker_filename` — basename only, validated like compose (e.g. `hat.png`).
   - `last_compose_x`, `last_compose_y` — integers as passed into compose.
   - `last_compose_scale` — real, same allowed range as compose (e.g. `0.05`–`1.0`).
   - `last_compose_angle_deg` — real, same allowed range as compose (e.g. `-180`–`180`).

2. **When metadata is written**  
   On successful **Save** (`POST /editor/save`), after the temp file is moved and the row is inserted (or updated), persist the **current** last-compose snapshot taken from session values that reflect the **last successful** `POST /editor/compose` for the current workspace.

3. **Session source of truth**  
   After each successful compose, update session keys holding that compose’s sticker and numeric parameters (recommended naming pattern: `editor_last_compose_*`, aligned with existing editor session hygiene).

4. **Invalidation**  
   On upload, capture, reset, or any workspace change that replaces the temp image without a matching compose, clear or invalidate these session keys so a save cannot attach sticker metadata from a previous base.

5. **Out of scope for this feature**

   - Full compose history per image (multiple applies logged).
   - Gallery or editor UI that displays this metadata (unless explicitly added as a minimal single-line caption in a follow-up).
   - Re-opening the editor and reconstructing the workspace from saved metadata.

## Constraints

- **MVC**: persistence belongs in the repository layer; controllers orchestrate session + validated inputs; no HTML in repositories.
- **Security**: sticker filename must follow the same validation rules as `EditorController::compose` / `StickerService` — no arbitrary paths from the client stored.
- **Migrations**: document `ALTER TABLE` for existing SQLite databases in comments near `database/schema.sql` (project pattern); new installs use the updated schema.

## Success Criteria

- [ ] After compose → save, the new `images` row includes non-null last-compose fields matching the last successful compose for that workspace.
- [ ] Replacing the base (upload/capture/reset) clears or invalidates compose metadata in session so save does not reuse stale sticker/geometry from an earlier image.
- [ ] Rows created before this feature remain valid (NULL compose columns or documented defaults).
- [ ] `database/schema.sql` reflects the new columns and includes a one-line migration note for existing DBs.

## Implementation Plan

1 add nullable compose-metadata columns to `images` in `database/schema.sql` plus a short `ALTER TABLE` migration note for existing SQLite DBs
2 extend `ImageRepository` insert (or a small dedicated method) to accept optional last-compose fields and bind them on insert
3 after successful `POST /editor/compose`, store sticker basename and numeric parameters in `editor_last_compose_*` session keys
4 clear `editor_last_compose_*` wherever the workspace temp image is replaced without a new compose (upload, capture, reset, and parallel hygiene paths already touching editor session state)
5 on successful `POST /editor/save`, read the compose snapshot from session and pass it into the repository call so the new `images` row stores matching metadata

## Tests

**Test cases**

- Success: compose then save writes `last_sticker_filename`, `last_compose_x`, `last_compose_y`, `last_compose_scale`, and `last_compose_angle_deg` on the new `images` row.
- Success: multiple compose actions before one save persist only the latest successful compose values.
- Failure: save without authenticated session redirects to `/login` and does not create/modify metadata.
- Failure: save when no valid temp workspace image exists returns save error path and does not create a new `images` row.
- Edge: upload (or capture/reset) after a previous compose and before save clears invalidated compose snapshot, so saved row does not reuse stale compose values.
- Edge: pre-existing `images` rows (created before schema extension) remain readable with NULL compose metadata.

**Execute tests**

```bash
#!/usr/bin/env bash
set -euo pipefail

BASE="${BASE:-http://localhost:8080}"
DB_PATH="${DB_PATH:-database/camagru.db}"
COOKIE_JAR="${COOKIE_JAR:-/tmp/camagru-issue20-cookies.txt}"
TEST_NONCE="${TEST_NONCE:-$(date +%s)}"
TEST_EMAIL="${TEST_EMAIL:-issue20_${TEST_NONCE}@example.com}"
TEST_USERNAME="${TEST_USERNAME:-issue20_u_${TEST_NONCE}}"
PASSWORD="${PASSWORD:-Test1234!}"
STICKER="${STICKER:-hat.png}"
FIXTURE_PATH="${FIXTURE_PATH:-tests/fixtures/base.png}"

rm -f "$COOKIE_JAR"

sql() {
  sqlite3 "$DB_PATH" "$1"
}

sql_escape() {
  printf "%s" "$1" | sed "s/'/''/g"
}

# Create a GD-generated PNG fixture if missing (320x240 truecolor).
if [ ! -f "$FIXTURE_PATH" ]; then
  mkdir -p "$(dirname "$FIXTURE_PATH")"
  php -r '
    if (!function_exists("imagecreatetruecolor") || !function_exists("imagepng")) {
      fwrite(STDERR, "ERROR: PHP GD is required to generate tests/fixtures/base.png\n");
      exit(1);
    }
    $path = $argv[1];
    $img = imagecreatetruecolor(320, 240);
    if ($img === false) {
      fwrite(STDERR, "ERROR: Failed to allocate GD image for fixture\n");
      exit(1);
    }
    $bg = imagecolorallocate($img, 230, 230, 230);
    if ($bg === false || !imagefilledrectangle($img, 0, 0, 319, 239, $bg)) {
      imagedestroy($img);
      fwrite(STDERR, "ERROR: Failed to paint fixture background\n");
      exit(1);
    }
    $fg = imagecolorallocate($img, 40, 40, 40);
    if ($fg !== false) {
      imagestring($img, 5, 12, 12, "camagru issue20 fixture", $fg);
    }
    if (!imagepng($img, $path)) {
      imagedestroy($img);
      fwrite(STDERR, "ERROR: Failed to write PNG fixture\n");
      exit(1);
    }
    imagedestroy($img);
  ' "$FIXTURE_PATH"
fi

# 1) Register user
REGISTER_BODY="$(mktemp)"
REGISTER_CODE="$(curl -sS -o "$REGISTER_BODY" -w "%{http_code}" -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
  -X POST "$BASE/register" \
  --data-urlencode "email=$TEST_EMAIL" \
  --data-urlencode "username=$TEST_USERNAME" \
  --data-urlencode "password=$PASSWORD" \
  --data-urlencode "confirm_password=$PASSWORD")"
echo "register_status=$REGISTER_CODE"

# 2) Confirm user (login requires email_verified=1)
SAFE_EMAIL="$(sql_escape "$TEST_EMAIL")"
TOKEN="$(sql "SELECT confirmation_token FROM users WHERE email = '$SAFE_EMAIL' ORDER BY id DESC LIMIT 1;")"
if [ -z "$TOKEN" ]; then
  echo "ERROR: confirmation_token missing for $TEST_EMAIL"
  echo "register_body_excerpt:"
  sed -n '1,40p' "$REGISTER_BODY"
  exit 1
fi

CONFIRM_BODY="$(mktemp)"
CONFIRM_CODE="$(curl -sS -o "$CONFIRM_BODY" -w "%{http_code}" -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
  "$BASE/register/confirm?token=$TOKEN")"
echo "confirm_status=$CONFIRM_CODE"

VERIFIED="$(sql "SELECT email_verified FROM users WHERE email = '$SAFE_EMAIL' ORDER BY id DESC LIMIT 1;")"
echo "email_verified=$VERIFIED (expected 1)"
test "$VERIFIED" = "1"

# 3) Login
LOGIN_BODY="$(mktemp)"
LOGIN_CODE="$(curl -sS -o "$LOGIN_BODY" -w "%{http_code}" -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
  -X POST "$BASE/login" \
  --data-urlencode "username=$TEST_USERNAME" \
  --data-urlencode "password=$PASSWORD")"
echo "login_status=$LOGIN_CODE"

# 4) Verify authenticated access to editor before editor actions
EDITOR_BODY="$(mktemp)"
EDITOR_CODE="$(curl -sS -o "$EDITOR_BODY" -w "%{http_code}" -c "$COOKIE_JAR" -b "$COOKIE_JAR" "$BASE/editor")"
echo "editor_status=$EDITOR_CODE (expected 200)"
test "$EDITOR_CODE" = "200"

# 5) Upload base image (capture code + body)
UPLOAD_BODY="$(mktemp)"
UPLOAD_CODE="$(curl -sS -o "$UPLOAD_BODY" -w "%{http_code}" -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
  -X POST "$BASE/editor/upload" \
  -F "base_image=@$FIXTURE_PATH;type=image/png" \
  -F "sticker=$STICKER")"
echo "upload_status=$UPLOAD_CODE"
if [ "$UPLOAD_CODE" -ne 302 ]; then
  echo "upload_body_excerpt:"
  sed -n '1,80p' "$UPLOAD_BODY"
fi

# 6) Compose once (capture code + body)
COMPOSE1_BODY="$(mktemp)"
COMPOSE1_CODE="$(curl -sS -o "$COMPOSE1_BODY" -w "%{http_code}" -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
  -X POST "$BASE/editor/compose" \
  --data-urlencode "sticker=$STICKER" \
  --data-urlencode "x=120" \
  --data-urlencode "y=60" \
  --data-urlencode "scale=0.45" \
  --data-urlencode "angle=15")"
echo "compose1_status=$COMPOSE1_CODE"
if [ "$COMPOSE1_CODE" -ne 302 ]; then
  echo "compose1_body_excerpt:"
  sed -n '1,120p' "$COMPOSE1_BODY"
fi

# 7) Compose again (latest should win; capture code + body)
COMPOSE2_BODY="$(mktemp)"
COMPOSE2_CODE="$(curl -sS -o "$COMPOSE2_BODY" -w "%{http_code}" -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
  -X POST "$BASE/editor/compose" \
  --data-urlencode "sticker=$STICKER" \
  --data-urlencode "x=140" \
  --data-urlencode "y=80" \
  --data-urlencode "scale=0.55" \
  --data-urlencode "angle=5")"
echo "compose2_status=$COMPOSE2_CODE"
if [ "$COMPOSE2_CODE" -ne 302 ]; then
  echo "compose2_body_excerpt:"
  sed -n '1,120p' "$COMPOSE2_BODY"
fi

if [ "$COMPOSE1_CODE" -ne 302 ] || [ "$COMPOSE2_CODE" -ne 302 ]; then
  echo "ERROR: compose did not reach expected redirect success path."
  exit 1
fi

# 8) Save and verify redirect (capture code + body)
SAVE_BODY="$(mktemp)"
SAVE_STATUS="$(curl -sS -o "$SAVE_BODY" -w "%{http_code}" -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/editor/save")"
echo "save_status=$SAVE_STATUS (expected 302)"
if [ "$SAVE_STATUS" -ne 302 ]; then
  echo "save_body_excerpt:"
  sed -n '1,120p' "$SAVE_BODY"
fi
test "$SAVE_STATUS" = "302"

# 9) Verify latest row metadata (success + latest compose wins)
sql "SELECT image_path,last_sticker_filename,last_compose_x,last_compose_y,last_compose_scale,last_compose_angle_deg FROM images ORDER BY id DESC LIMIT 1;"

# 10) Failure case: unauthenticated save must redirect/login path
UNAUTH_STATUS="$(curl -sS -o /dev/null -w "%{http_code}" -X POST "$BASE/editor/save")"
echo "unauth_save_status=$UNAUTH_STATUS (expected 302)"
test "$UNAUTH_STATUS" = "302"

# 11) Edge setup: upload new base after compose, then save; metadata must be NULL/empty
UPLOAD2_BODY="$(mktemp)"
UPLOAD2_CODE="$(curl -sS -o "$UPLOAD2_BODY" -w "%{http_code}" -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
  -X POST "$BASE/editor/upload" \
  -F "base_image=@$FIXTURE_PATH;type=image/png" \
  -F "sticker=$STICKER")"
echo "upload2_status=$UPLOAD2_CODE"
if [ "$UPLOAD2_CODE" -ne 302 ]; then
  echo "upload2_body_excerpt:"
  sed -n '1,80p' "$UPLOAD2_BODY"
fi

SAVE2_BODY="$(mktemp)"
SAVE2_CODE="$(curl -sS -o "$SAVE2_BODY" -w "%{http_code}" -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/editor/save")"
echo "save2_status=$SAVE2_CODE"
if [ "$SAVE2_CODE" -ne 200 ] && [ "$SAVE2_CODE" -ne 302 ]; then
  echo "save2_body_excerpt:"
  sed -n '1,120p' "$SAVE2_BODY"
fi
sql "SELECT image_path,last_sticker_filename,last_compose_x,last_compose_y,last_compose_scale,last_compose_angle_deg FROM images ORDER BY id DESC LIMIT 1;"

# 12) Edge: legacy rows remain readable (manual check if needed)
sql "SELECT COUNT(*) FROM images WHERE last_sticker_filename IS NULL;"
```
