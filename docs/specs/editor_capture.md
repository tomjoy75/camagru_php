# Feature: `editor_capture`

Goal
Close the editor creation loop by wiring the Capture control to the existing webcam preview flow and aligning upload behavior with the same composition/save pipeline.

Behavior
- On `/editor`, an authenticated user with webcam permission can click Capture to snapshot the current video frame.
- The captured frame is sent through the same server flow used by editor composition (compose + save), so the result becomes a real saved image.
- Upload remains available and follows consistent UX rules with capture (clear feedback, same destination flow, same editor result area behavior).
- If capture is unavailable (no camera permission, browser/device unsupported), the UI keeps upload usable and shows a non-blocking error message.
- After success (capture or upload path), the user is redirected back to `/editor` and can see the resulting image in the expected editor context.

Constraints
- MVC boundaries must be preserved: controller handles HTTP/session flow, service handles business/image logic, view/JS handles UI only.
- Capture and upload actions require authentication (same access control as editor page).
- Server-side validation remains authoritative for incoming image data; client-side checks are UX-only.
- No external framework/library; project stack stays PHP standard library + vanilla JS.
- Keep scope minimal for issue #17: wire capture and align upload controls only, without adding likes/comments/gallery side features.

Success Criteria
- A logged-in user can capture from webcam and obtain a saved composed image through the existing editor backend flow.
- Existing upload path still works and UX is consistent with capture flow outcomes.
- Failure cases are handled safely (no auth, denied camera permission, invalid payload) with user-visible feedback and no server error leak.
- Core editor flow remains stable: preview -> capture/upload -> compose/save -> visible result on return to `/editor`.

## Implementation Plan

1 add capture form wiring in `editor.php` (hidden `base_image` field + capture submit action)
2 update `editor_webcam_preview.js` to snapshot video frame into the hidden field before submit
3 enforce auth + request method + required payload checks in capture handling endpoint
4 route capture payload into existing compose/save service flow used by editor submit
5 align upload control post-submit feedback/redirect path with capture flow result
6 render unified success/error flash area in `editor.php` for both capture and upload outcomes

## Tests

**Test cases**
- Success: authenticated capture submission redirects to `/editor` and the editor preview/save flow remains usable.
- Success: authenticated file upload still redirects to `/editor` and behaves consistently with capture outcomes.
- Failure: unauthenticated capture/upload attempt is rejected safely (redirect/login) without processing image data.
- Failure: invalid capture payload (missing/invalid image data) returns safe error feedback, no 500.
- Edge: capture unavailable in browser does not block upload path; upload still works.
- Edge: very small valid image payload (1x1 PNG) is accepted by server-side validation.

**Test Setup (authentication)**
```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies.txt"

rm -f "$COOKIE_JAR"

# Register user (ignore failure if already exists)
curl -sS -i -c "$COOKIE_JAR" -X POST "$BASE/register" \
  -d "email=capture.test@example.com" \
  -d "username=capturetest" \
  -d "password=Test1234!" \
  -d "confirm_password=Test1234!" >/tmp/capture_register.out

# Login user
curl -sS -i -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/login" \
  -d "email=capture.test@example.com" \
  -d "password=Test1234!" >/tmp/capture_login.out
```

**Execute tests**
```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies.txt"

# Create a tiny valid PNG fixture for upload checks
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQmY9WkAAAAASUVORK5CYII=' | base64 -d > /tmp/editor_capture_1x1.png

# S1: Authenticated upload path still works
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/editor_capture_1x1.png;type=image/png"

# S2: Authenticated capture payload works (adjust field/route to final implementation)
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/capture" \
  --data-urlencode "base_image_data=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQmY9WkAAAAASUVORK5CYII="

# F1: Unauthenticated upload is rejected safely
curl -sS -i -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/editor_capture_1x1.png;type=image/png"

# F2: Invalid capture payload is rejected safely
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/capture" \
  --data-urlencode "base_image_data=not-an-image"

# E1: Missing capture payload is rejected safely
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/capture"
```
