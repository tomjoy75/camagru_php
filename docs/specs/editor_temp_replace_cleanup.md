# Feature: `editor_temp_replace_cleanup`

Goal

Keep at most one active editor workspace temp file per session for superseded images: whenever a **new** temp image is successfully created and the session’s `editor_temp_image` is updated (after capture, upload, or compose), remove the **previous** temp file that was referenced in session—so `public/tmp/` does not accumulate orphan files across repeated actions in the same visit.

Behavior

- After a flow successfully produces a new workspace temp basename and the session is about to point at it (or immediately after updating `editor_temp_image`), if the session previously held another temp basename, attempt to delete that **old** file.
- Deletion applies only when the old name matches the project’s editor temp filename rules (same pattern and validation as reset and other editor tmp handling, e.g. `img_[16 hex].png|jpg` under `public/tmp/` only).
- Never delete paths under `public/uploads/` or any directory other than `public/tmp/` for this logic; never treat arbitrary user input as a filesystem path outside the validated basename rules.
- If there was no previous temp in session, or the old file is already missing, the flow completes without error (no 500).
- Existing reset-workspace behavior stays unchanged for the **current** temp when the user explicitly resets; this feature only covers **replacement** of one temp by another during capture, upload, or compose.

Constraints

- MVC: keep orchestration in the controller (or a tiny shared helper used only for “safe unlink previous editor temp” to avoid duplicating validation rules); no new business logic in views.
- PHP standard library only; no new dependencies.
- Auth and session rules for those endpoints stay as today; this cleanup runs only as part of successful server-side paths that already set `editor_temp_image`.
- No cron jobs, no full-directory scans of `public/tmp/`, no cleanup of other users’ files.

Success Criteria

- Repeated capture, upload, and/or compose in one authenticated session does not leave unbounded old temp files for **superseded** workspace images (only the current session temp and any files still referenced elsewhere may remain).
- Reset continues to behave as specified in `docs/specs/editor_reset_workspace.md` for clearing the active workspace temp.
- Invalid session values, non-matching filenames, or missing files do not cause deletes outside `public/tmp/` or uncaught errors (no 500 from this cleanup step).

## Implementation Plan

1 add private `EditorController` helper: unlink `public/tmp/{basename}` for one raw session value using the same `basename` / `isValidEditorTempFilename` / `is_file` rules as `reset()`
2 `upload()` success: stash prior `editor_temp_image`, set new filename, call helper on stash when nonempty and ≠ new basename
3 `capture()` success: same as step 2
4 `compose()` success: same as step 2
5 verify authenticated chained upload/capture/compose: superseded tmp files removed; error paths unchanged

## Tests

**Test cases**

- **Success:** Two consecutive authenticated uploads to `/editor/upload`; after the second, the first temp basename no longer exists under `public/tmp/`, and the second does.
- **Success:** Authenticated upload then successful `POST /editor/compose` with a valid sticker; the pre-compose temp basename is gone from `public/tmp/`, and the new workspace temp exists.
- **Success:** Authenticated capture then upload (or upload then capture); the earlier workspace temp file under `public/tmp/` is removed when superseded.
- **Failure:** Authenticated upload succeeds, then a second `POST /editor/upload` with an invalid/non-image file; session still holds the first temp, and `public/tmp/<first basename>` still exists (no cleanup on failed replace).
- **Failure:** Unauthenticated `POST /editor/upload` returns safe rejection (e.g. redirect to login); no reliance on temp cleanup behavior for guests.
- **Edge:** First editor visit with no prior `editor_temp_image`, then one successful upload; no error, one temp file present (no “previous” to delete).
- **Edge:** Two successful uploads produce different basenames; confirm `public/uploads/` is unchanged by this feature (manual spot-check or existing save flow).

**Test Setup (authentication)**

```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies-tmpcleanup.txt"
REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"

rm -f "$COOKIE_JAR"

curl -sS -i -c "$COOKIE_JAR" -X POST "$BASE/register" \
  -d "email=tmpcleanup@example.com" \
  -d "username=tmpcleanup" \
  -d "password=Test1234!" \
  -d "confirm_password=Test1234!" >/tmp/tmpcleanup_register.out

curl -sS -i -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/login" \
  -d "username=tmpcleanup" \
  -d "password=Test1234!" >/tmp/tmpcleanup_login.out

printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQmY9WkAAAAASUVORK5CYII=' | base64 -d > /tmp/tmpcleanup_1x1.png
```

**Execute tests**

```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies-tmpcleanup.txt"
REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$REPO_ROOT"

# Helper: current workspace tmp basename from editor HTML (empty if webcam-only / no preview)
editor_tmp_basename() {
  curl -sS -b "$COOKIE_JAR" "$BASE/editor" | grep -oE 'img_[a-f0-9]{16}\.(png|jpg)' | head -1
}

# S1: Double upload — first tmp removed, second remains
curl -sS -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/tmpcleanup_1x1.png;type=image/png"
TMP1="$(editor_tmp_basename)"
curl -sS -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/tmpcleanup_1x1.png;type=image/png"
TMP2="$(editor_tmp_basename)"
test -n "$TMP1" && test -n "$TMP2" && test "$TMP1" != "$TMP2"
test ! -f "public/tmp/$TMP1" && test -f "public/tmp/$TMP2"

# S2: Upload then compose — sticker name from first compose form on /editor
STICKER="$(curl -sS -b "$COOKIE_JAR" "$BASE/editor" | sed -n 's/.*name="sticker" value="\([^"]*\)".*/\1/p' | head -1)"
curl -sS -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/tmpcleanup_1x1.png;type=image/png"
TMP_BEFORE="$(editor_tmp_basename)"
curl -sS -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/compose" \
  -d "sticker=$STICKER" -d "x=50" -d "y=50"
TMP_AFTER="$(editor_tmp_basename)"
test -n "$TMP_BEFORE" && test -n "$TMP_AFTER" && test "$TMP_BEFORE" != "$TMP_AFTER"
test ! -f "public/tmp/$TMP_BEFORE" && test -f "public/tmp/$TMP_AFTER"

# S3: Capture then upload (requires stickers dir non-empty for S2; capture uses same PNG as editor_capture spec)
curl -sS -o /dev/null -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/capture" \
  --data-urlencode "base_image_data=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQmY9WkAAAAASUVORK5CYII="
TMP_CAP="$(editor_tmp_basename)"
curl -sS -o /dev/null -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/tmpcleanup_1x1.png;type=image/png"
TMP_UP="$(editor_tmp_basename)"
test -n "$TMP_CAP" && test -n "$TMP_UP" && test "$TMP_CAP" != "$TMP_UP"
test ! -f "public/tmp/$TMP_CAP" && test -f "public/tmp/$TMP_UP"

# F1: Bad second upload — first file on disk should remain
curl -sS -o /dev/null -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/tmpcleanup_1x1.png;type=image/png"
KEEP="$(editor_tmp_basename)"
curl -sS -o /dev/null -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=@/etc/hostname;type=image/png"
test -f "public/tmp/$KEEP"

# F2: Guest upload rejected (no cookie jar)
curl -sS -i -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/tmpcleanup_1x1.png;type=image/png" | head -5
```

Notes:

- Run with the PHP app reachable at `BASE` and at least one sticker file under `public/stickers/` so **S2** can resolve `STICKER` (empty `STICKER` skips meaningful compose assertions—add assets or adjust).
- **S1/S2/S3/F1** use `test` at the end of each block; a non-zero exit means failure. Adjust `BASE` and paths if your server or cookie policy differs.
