# Feature: `editor_reset_workspace`

Goal

Let an authenticated user clear the editor workspace session state and return to the live webcam preview area (when no base image is loaded), so they can start a new capture or upload in the same visit—especially after a successful save, when the UI otherwise keeps showing the last workspace image from `uploads/`.

Behavior

- On `/editor`, when the user chooses a explicit “reset workspace” / “new capture” action, the server clears `editor_temp_image` from the session (and any editor flash keys used only for this flow, if needed for consistency).
- After reset, the response redirects to `GET /editor` (or equivalent) so the main preview area uses the same rules as today: with no temp image in session, the view shows the live `<video>` branch (and webcam script can attach again) instead of an `<img>` sourced from `/tmp/…` or `/uploads/…`.
- Capture, upload, compose, and save continue to work unchanged after reset; the user must load a new base image before compose/save behave as today.
- If the session still referenced a file that exists only under `public/tmp/` (e.g. composed-but-not-saved image), the server may remove that file when clearing workspace, using the same filename validation rules as the rest of the editor (`img_[16 hex].png|jpg`). Files under `public/uploads/` must never be deleted by this action.
- Unauthenticated requests to the reset action are rejected the same way as other editor mutations (e.g. redirect to login), with no workspace change.

Constraints

- Preserve MVC boundaries: HTTP/session orchestration in the controller; no business logic in views; optional small service/helper only if it avoids duplicating filename/path rules already used for editor temp files.
- PHP standard library only; no new front-end frameworks; vanilla JS only if a minimal client trigger is required (e.g. unchanged pattern from existing capture/upload forms).
- Auth required; validate any filesystem operation: only delete under `public/tmp/`, only for names matching the project’s editor temp filename pattern, never follow user-supplied paths outside that directory.
- Do not change sticker assets, composition rules, or routing for unrelated editor endpoints beyond adding the reset entry point.
- User-visible strings in views must be escaped with `htmlspecialchars` like the rest of the app.

Success Criteria

- After saving an image, an authenticated user can reset the workspace and land on `/editor` with the webcam preview area active again (no stale `<img>` for the previous workspace file), while upload and capture still work.
- With a composed image still in `tmp/` only, reset clears session and does not leave an unusable editor state; optional tmp cleanup does not remove anything from `uploads/`.
- Unauthenticated reset attempts are handled safely (no session mutation for guests, no 500).
- Wrong HTTP method on the reset route is rejected safely, consistent with other editor POST actions.

## Implementation Plan

1. wire `POST /editor/reset` in `src/routes/index.php` to `EditorController::reset()`
2. `EditorController::reset()` auth gate; unset `editor_temp_image`; redirect `/editor`
3. before unset, capture basename; after unset, `unlink` `public/tmp/{basename}` only if valid temp name and file exists
4. set `editor_success` flash then redirect
5. add reset POST form in `src/views/editor.php`

## Tests

**Test cases**

- **Success:** Authenticated `POST /editor/reset` returns a redirect to `/editor`; after a prior upload in the same session, `GET /editor` shows the webcam preview markup (`id="editor-webcam-preview"`), not a workspace `<img>`.
- **Success:** Authenticated `POST /editor/reset` when the session has no `editor_temp_image` still completes safely (redirect, no 500).
- **Failure:** Unauthenticated `POST /editor/reset` is rejected like other editor mutations (e.g. redirect to `/login`), with no reset applied.
- **Failure:** Unsupported method on the reset path (e.g. `GET /editor/reset`) does not execute the reset handler (e.g. 404), consistent with other strict `POST` editor routes.
- **Edge:** Two consecutive authenticated `POST /editor/reset` requests both succeed without error.
- **Edge:** Reset does not delete anything under `public/uploads/`; only optional cleanup under `public/tmp/` for valid editor temp names (verify manually or with a composed-not-saved flow if needed).

**Test Setup (authentication)**

```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies-resetws.txt"

rm -f "$COOKIE_JAR"

# Register user (ignore failure if already exists)
curl -sS -i -c "$COOKIE_JAR" -X POST "$BASE/register" \
  -d "email=resetws@example.com" \
  -d "username=resetwsuser" \
  -d "password=Test1234!" \
  -d "confirm_password=Test1234!" >/tmp/resetws_register.out

# Login
curl -sS -i -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/login" \
  -d "username=resetwsuser" \
  -d "password=Test1234!" >/tmp/resetws_login.out

# Tiny PNG for upload
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQmY9WkAAAAASUVORK5CYII=' | base64 -d > /tmp/resetws_1x1.png
```

**Execute tests**

```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies-resetws.txt"

# S1: Upload loads workspace (preview img path, not webcam id in main slot)
curl -sS -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/resetws_1x1.png;type=image/png" >/tmp/resetws_upload.out
curl -sS -b "$COOKIE_JAR" "$BASE/editor" -o /tmp/resetws_editor_s1.html
grep -q 'id="editor-webcam-preview"' /tmp/resetws_editor_s1.html && echo "FAIL: expected img workspace after upload" || echo "OK: no webcam id (img branch)"

# S2: Authenticated reset → redirect to /editor
curl -sS -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/reset" -o /tmp/resetws_reset.body -D /tmp/resetws_reset.hdr
grep -qiE '^Location: .*/editor' /tmp/resetws_reset.hdr && echo "OK: redirect to editor" || echo "FAIL: missing Location /editor"

# S3: After reset, editor page shows webcam preview element again (save body first: avoids curl exit 23 with pipefail when grep -q exits early)
curl -sS -b "$COOKIE_JAR" "$BASE/editor" -o /tmp/resetws_editor_s3.html
grep -q 'id="editor-webcam-preview"' /tmp/resetws_editor_s3.html && echo "OK: webcam preview after reset" || echo "FAIL: expected webcam preview markup"

# S4: Reset with empty workspace (second reset in a row)
curl -sS -i -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/editor/reset" -D /tmp/resetws_reset2.hdr -o /dev/null
grep -qiE '^Location: .*/editor' /tmp/resetws_reset2.hdr && echo "OK: second reset redirect" || echo "FAIL: second reset"

# F1: Unauthenticated reset
rm -f /tmp/resetws_guest.txt
curl -sS -i -c /tmp/resetws_guest.txt -X POST "$BASE/editor/reset" -D /tmp/resetws_unauth.hdr -o /dev/null
grep -qiE '^Location: .*/login' /tmp/resetws_unauth.hdr && echo "OK: guest redirected to login" || echo "FAIL: unauthenticated should redirect login"

# F2: Wrong HTTP method (GET) on reset path — expect 404 if only POST is registered
curl -sS -o /dev/null -w "GET /editor/reset HTTP %{http_code}\n" -b "$COOKIE_JAR" "$BASE/editor/reset"
```
