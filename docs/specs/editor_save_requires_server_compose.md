# Feature: Editor save requires server-side compose (current workspace)

## Goal

Ensure no image is moved into durable gallery storage (`public/uploads/` + `images` row) until the server has successfully composed at least one sticker onto the **current** session workspace file in `public/tmp/`. This aligns the saved file with what the server has validated, closes the gap where client-only previews could diverge from the stored image, and prevents bypassing a hidden/disabled Save button with a direct `POST /editor/save`.

## Behavior

1. **Workspace compose gate (session)**  
   Maintain a server-side flag (or equivalent) tied to the current editor temp workspace: it is **false/unset** whenever the base temp file is not yet “compose-authorized” for this workspace.

2. **Clear the gate** when the workspace base changes or is removed: on successful paths that establish or replace the temp base (`upload`, `capture`), and on **workspace reset** when there is no valid base to save. After any of these, saving to the gallery is not allowed until the next successful compose.

3. **Set the gate** only after a **successful** `POST /editor/compose` for the current session temp image (same rules as today for which file is the compose target). A failed compose leaves the gate unset/false.

4. **`POST /editor/save`**  
   In addition to existing auth, temp file presence, and filename validation (see `docs/specs/editor_save.md`): if the compose gate is not satisfied for the **current** `$_SESSION['editor_temp_image']`, do not move the file or insert a DB row; respond with a clear, user-visible error (and consistent HTTP behavior with the rest of the editor, e.g. re-render editor with message).

5. **`GET /editor` (view)**  
   Expose whether save is allowed; show the Save control only when allowed, or disable it with the same rule. Direct forged `POST /editor/save` must still be rejected when the gate is false.

6. **Out of scope**  
   No full UI rewrite, no SPA, no change to `ImageComposeService` merge logic unless strictly required to wire the gate.

## Constraints

- **Server is authoritative:** UI hiding/disabling Save is not sufficient; `save()` must enforce the rule.
- **Tie the flag to the workspace:** a new upload, capture, or reset that changes or invalidates the base must invalidate any previous “compose OK” for an old file name.
- **Failed compose does not authorize save.**
- Reuse existing session temp key and path validation patterns; do not trust client-provided filenames for save (unchanged from editor save spec).

## Success Criteria

1. After **upload** or **capture** with **no** subsequent successful compose, the user cannot save to the gallery (Save hidden or disabled, and `POST /editor/save` rejected with an explicit message).
2. After a **successful** `POST /editor/compose` on the current workspace, save is allowed (subject to existing save validation).
3. A new **upload** or **capture** that replaces the temp file **resets** the requirement: save blocked again until the next successful compose.
4. After **workspace reset** (no saveable base / flow per reset spec), save is not possible without going through a new base → compose cycle.
5. A **failed** compose does not set the gate; save remains blocked.

## Related specs

- `docs/specs/editor_save.md` — persist flow, validation, move/DB behavior.
- `docs/specs/editor_compose.md` — compose endpoint and temp output.
- `docs/specs/editor_reset_workspace.md` — reset clears workspace expectations.

## Post-feature cleanup / tech debt

- Optional follow-up: GitHub issue #63 (clarify preview vs. server-applied sticker in the UI); not required to meet the gate above.

## Implementation Plan

1 clear compose-authorized session state on editor upload, capture, and workspace reset when the temp base changes or is removed
2 set compose-authorized session state only on successful POST /editor/compose, bound to the current `editor_temp_image` basename
3 reject POST /editor/save in EditorController::save when the gate is false for the current temp file; re-render editor with explicit error, no move or DB insert
4 compute `editor_can_save` (or equivalent) in the GET /editor action from session and temp presence; pass into the editor view payload
5 in editor.php, show or enable the Save image control only when `editor_can_save` is true

## Tests

**Test cases**

- **Success:** After upload and a **successful** `POST /editor/compose`, `GET /editor` includes the save form (`action="/editor/save"`) and `POST /editor/save` responds with **302** to `/editor` (file moved, session cleared per existing save behavior).
- **Success:** After upload + successful compose, `GET /editor` does not show a save-related error for the gate (normal editor load).
- **Failure:** After upload **without** compose, `POST /editor/save` returns **200** with an HTML body containing a visible save error (`text-red-600`) and a gate-related message; no **302**; image stays under `public/tmp/` (optional manual check).
- **Failure:** After upload **without** compose, `GET /editor` does **not** expose the save form (no `action="/editor/save"` in the body—button hidden when save is disallowed).
- **Failure:** Unauthenticated `POST /editor/save` → **302** to `/login` (unchanged).
- **Edge:** After upload + successful compose, a **second upload** replaces the temp file; `POST /editor/save` without another compose is rejected like the “no compose” case (gate reset for new basename).
- **Edge:** **Failed** compose (e.g. invalid coordinates or invalid sticker) does not open save: `GET /editor` has no save form and `POST /editor/save` still rejected until a later successful compose.
- **Edge:** After **`POST /editor/reset`** with a prior workspace, `POST /editor/save` does not persist a gallery row (no temp / existing “nothing to save” behavior); no bypass of the compose rule once a new base is loaded without compose.

**Test Setup (authentication)**

```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies-editor-save-gate.txt"

rm -f "$COOKIE_JAR"

# Register (ignore if user already exists)
curl -sS -c "$COOKIE_JAR" -X POST "$BASE/register" \
  -d "email=savegate@example.com" \
  -d "username=savegateuser" \
  -d "password=Test1234!" \
  -d "confirm_password=Test1234!" -o /dev/null

# Login
curl -sS -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/login" \
  -d "username=savegateuser" \
  -d "password=Test1234!" -o /dev/null
```

**Execute tests**

```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies-editor-save-gate.txt"
# Use a repo-relative path from project root so GD compose has a real-sized base
UPLOAD='@public/stickers/glasses.png;type=image/png'

# F0: POST /editor/save without session → redirect to login
curl -sS -i -X POST "$BASE/editor/save" -o /dev/null -D /tmp/savegate_f0.hdr
grep -qiE '^Location: .*/login' /tmp/savegate_f0.hdr && echo "OK: guest save → login" || echo "FAIL: guest save"

# Load workspace (upload)
curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=$UPLOAD" -o /dev/null

# F1: Save without compose → 200, error markup, no redirect to /editor for success
curl -sS -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/save" -D /tmp/savegate_f1.hdr -o /tmp/savegate_f1.body
grep -qE '^HTTP/[0-9.]+ 200' /tmp/savegate_f1.hdr && grep -q 'text-red-600' /tmp/savegate_f1.body && grep -qi 'compose' /tmp/savegate_f1.body \
  && echo "OK: save blocked without compose (200 + message)" || echo "FAIL: expected 200 + red error + compose hint"
! grep -qE '^Location: .*/editor' /tmp/savegate_f1.hdr && echo "OK: no success redirect" || echo "FAIL: save should not 302"

# F2: GET /editor after upload only → no save form
curl -sS -b "$COOKIE_JAR" "$BASE/editor" -o /tmp/savegate_editor_upload_only.html
! grep -q 'action="/editor/save"' /tmp/savegate_editor_upload_only.html && echo "OK: no save form before compose" || echo "FAIL: save form visible too early"

# S1: Successful compose → redirect to /editor
curl -sS -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=40&y=40" -D /tmp/savegate_compose.hdr -o /dev/null
grep -qiE '^Location: .*/editor' /tmp/savegate_compose.hdr && echo "OK: compose redirect" || echo "FAIL: compose should redirect"

# S2: GET /editor after compose → save form present
curl -sS -b "$COOKIE_JAR" "$BASE/editor" -o /tmp/savegate_editor_after_compose.html
grep -q 'action="/editor/save"' /tmp/savegate_editor_after_compose.html && echo "OK: save form after compose" || echo "FAIL: expected save form"

# S3: POST /editor/save after compose → 302 to /editor
curl -sS -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/save" -D /tmp/savegate_s3.hdr -o /dev/null
grep -qiE '^Location: .*/editor' /tmp/savegate_s3.hdr && echo "OK: save redirect" || echo "FAIL: save should 302 (re-run setup if DB/file collision)"

# E1: Failed compose does not open save (invalid y)
curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=$UPLOAD" -o /dev/null
curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=10&y=notanumber" -o /tmp/savegate_compose_bad.body
curl -sS -b "$COOKIE_JAR" "$BASE/editor" -o /tmp/savegate_editor_bad_compose.html
! grep -q 'action="/editor/save"' /tmp/savegate_editor_bad_compose.html && echo "OK: no save after failed compose" || echo "FAIL: save should stay closed"

# E2: Compose once, then new upload clears gate — save blocked until compose again
curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=40&y=40" -o /dev/null
curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=$UPLOAD" -o /dev/null
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/save" -D /tmp/savegate_e2.hdr -o /tmp/savegate_e2.body
grep -qE '^HTTP/[0-9.]+ 200' /tmp/savegate_e2.hdr && grep -qi 'compose' /tmp/savegate_e2.body \
  && echo "OK: re-upload cleared compose gate" || echo "FAIL: save should require compose again"

# E3: Reset clears workspace; save without image uses existing no-temp behavior
curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/reset" -o /dev/null
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/save" -D /tmp/savegate_e3.hdr -o /tmp/savegate_e3.body
grep -qE '^HTTP/[0-9.]+ 200' /tmp/savegate_e3.hdr && grep -q 'text-red-600' /tmp/savegate_e3.body && echo "OK: save with empty workspace errors" || echo "FAIL: reset then save"
```

Notes for implementers: pick a single user-visible gate string that includes the word **compose** (case-insensitive) so the `grep -qi 'compose'` checks stay stable; if the message is changed, update the grep in this spec accordingly.
