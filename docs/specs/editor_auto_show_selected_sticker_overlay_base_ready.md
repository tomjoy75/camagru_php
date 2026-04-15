# Feature: `editor_auto_show_selected_sticker_overlay_base_ready`

## Goal

Automatically display the currently selected sticker overlay when the Editor enters `BASE_READY` after creating a base image.

This keeps the sticker-first workflow continuous by removing the extra user action currently needed to make the selected sticker visible on the base image.

## Behavior

- When capture creates a base image and a sticker is already selected, the Editor auto-shows that sticker overlay in `BASE_READY`.
- When upload creates a base image and a sticker is already selected, the Editor auto-shows that sticker overlay in `BASE_READY`.
- If no sticker is selected when entering `BASE_READY`, no sticker overlay is auto-shown.
- The user can still reposition the visible overlay and explicitly apply the sticker through the existing flow.
- The feature reuses the existing client-side overlay and placement behavior; it does not trigger server-side composition by itself.

## Constraints

- Keep this feature limited to Editor UI/client behavior.
- Reuse existing overlay and placement wiring instead of introducing new workflow states.
- Do not change server composition behavior.
- Do not change save behavior.
- Do not add multi-sticker behavior.
- Do not redesign placement controls or Editor layout.

## Success Criteria

1. After capture with a preselected sticker, entry to `BASE_READY` immediately shows the selected sticker overlay.
2. After upload with a preselected sticker, entry to `BASE_READY` immediately shows the selected sticker overlay.
3. In those `BASE_READY` cases, the selected sticker source is present in compose state (`editor-compose-sticker` + overlay source).
4. If no sticker is selected at `BASE_READY` entry, no overlay is shown automatically.
5. Auto-show is an entry behavior only and must not repeatedly force-reset user-adjusted placement during later interactions.
6. After auto-show, existing reposition and explicit apply interactions still work as before.

## Implementation Plan

1. derive an `isBaseReady` + `selectedStickerPath` gate in `src/views/editor.php` from existing editor state and sticker hidden values
2. when the gate is true, render initial overlay placement attributes in the editor preview markup using the existing overlay container wiring
3. in `public/js/editor_sticker_placement.js`, add a guarded startup path that auto-displays overlay on first load only when initial overlay attributes are present
4. keep existing manual sticker selection behavior unchanged by skipping auto-show when no sticker is selected or when editor is not `BASE_READY`
5. verify both entry paths by checking capture-created and upload-created `BASE_READY` pages produce the same initial overlay behavior

## Tests

**Test cases**

- **Success:** With a preselected sticker, upload entry to `BASE_READY` keeps the selected sticker in compose state and loads placement UI.
- **Success:** With a preselected sticker, capture entry to `BASE_READY` keeps the selected sticker in compose state and loads placement UI.
- **Success:** On `BASE_READY` entry with preselected sticker, overlay becomes visible and uses the selected sticker source.
- **Failure:** Unauthenticated upload or capture still redirects to login (no regression).
- **Failure:** Invalid capture payload is rejected safely (no regression).
- **Edge:** Enter `BASE_READY` without selected sticker (if allowed by current flow) keeps compose sticker empty and does not auto-show overlay.
- **Edge:** After entry auto-show, moving/scaling/rotating the sticker is not force-reset by repeated auto-show while staying on the same workspace state.

**Test Setup (authentication)**
```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies.txt"

rm -f "$COOKIE_JAR"

# Register (ignore if already exists)
curl -sS -i -c "$COOKIE_JAR" -X POST "$BASE/register" \
  -d "email=editor67@example.com" \
  -d "username=editor67" \
  -d "password=Test1234!" \
  -d "confirm_password=Test1234!" >/tmp/editor67_register.out

# Login
curl -sS -i -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/login" \
  -d "username=editor67" \
  -d "password=Test1234!" >/tmp/editor67_login.out
```

**Execute tests**
```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies.txt"

# Fixture image
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQmY9WkAAAAASUVORK5CYII=' | base64 -d > /tmp/editor67_1x1.png

# Pick first available sticker name from editor page
STICKER=$(curl -sS -b "$COOKIE_JAR" "$BASE/editor" | sed -n 's/.*data-sticker="\([^"]*\)".*/\1/p' | head -n 1)
echo "Using sticker: $STICKER"

# Reset workspace
curl -sS -o /dev/null -b "$COOKIE_JAR" -X POST "$BASE/editor/reset"

# S1: upload with preselected sticker -> BASE_READY keeps compose sticker value
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "sticker=$STICKER" \
  -F "base_image=@/tmp/editor67_1x1.png;type=image/png" >/tmp/editor67_upload.out
curl -sS -b "$COOKIE_JAR" "$BASE/editor" -o /tmp/editor67_after_upload.html
grep -q "id=\"editor-compose-sticker\" value=\"$STICKER\"" /tmp/editor67_after_upload.html && echo "S1 OK" || echo "S1 CHECK"
grep -q "id=\"editor-sticker-stage\"" /tmp/editor67_after_upload.html && echo "S1 stage OK" || echo "S1 stage CHECK"

# S2: capture with preselected sticker -> BASE_READY keeps compose sticker value
curl -sS -o /dev/null -b "$COOKIE_JAR" -X POST "$BASE/editor/reset"
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/capture" \
  --data-urlencode "sticker=$STICKER" \
  --data-urlencode "base_image_data=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQmY9WkAAAAASUVORK5CYII=" >/tmp/editor67_capture.out
curl -sS -b "$COOKIE_JAR" "$BASE/editor" -o /tmp/editor67_after_capture.html
grep -q "id=\"editor-compose-sticker\" value=\"$STICKER\"" /tmp/editor67_after_capture.html && echo "S2 OK" || echo "S2 CHECK"

# F1: unauthenticated upload
curl -sS -i -X POST "$BASE/editor/upload" \
  -F "sticker=$STICKER" \
  -F "base_image=@/tmp/editor67_1x1.png;type=image/png"

# F2: invalid capture payload
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/capture" \
  --data-urlencode "sticker=$STICKER" \
  --data-urlencode "base_image_data=not-an-image"
```

**Manual / browser**

1. In `EMPTY`, select a sticker, then upload a base image: on `BASE_READY`, confirm overlay is visible immediately (without clicking a sticker again).
2. In `EMPTY`, select a sticker, then capture a base image: on `BASE_READY`, confirm overlay is visible immediately.
3. In each `BASE_READY` case above, confirm the shown overlay matches the selected sticker source.
4. In `BASE_READY`, drag/scale/rotate the sticker and wait/interact in place; confirm placement is not force-reset by repeated auto-show.
5. Click `Apply sticker`; confirm behavior is unchanged.
