# Feature: `editor_webcam_preview`

Goal  
Show a live webcam preview in the editor page for authenticated users so they can frame themselves before taking a picture.

Behavior  
On `GET /editor`, the page displays a video preview area linked to the user camera via browser APIs.  
If camera access is granted, the live stream is shown in the preview element.  
If camera access is denied, unavailable, or unsupported, the page stays usable and shows a clear non-blocking fallback message in the preview area.  
The feature is limited to preview only: it does not capture, upload, or persist images.

Constraints  
Follow existing MVC boundaries: controller/services handle request flow, view and client-side script handle preview rendering.  
No external JS libraries; use vanilla JavaScript only.  
Keep camera usage browser-local; no webcam data is sent to the server in this feature.  
Only authenticated users can access the editor page (existing access control remains the source of truth).  
Do not expand scope into capture/upload controls in this iteration.

Success Criteria  
An authenticated user opening `/editor` sees a live webcam stream when permission is granted.  
When permission is denied or camera is unavailable, the user sees an explicit fallback message and no server error occurs.  
The rest of the editor UI (stickers, existing controls, sidebar) still renders and works as before.  
No backend persistence or route changes are required for preview-only behavior.

## Implementation Plan

1 add a dedicated editor webcam preview script file and load it from the editor view
2 add a stable preview video element id and fallback message container in the editor view
3 in the script, guard for `navigator.mediaDevices.getUserMedia` availability and render unsupported fallback
4 request video stream with `getUserMedia` and bind the stream to the preview video element
5 handle permission denial or camera errors by showing a non-blocking fallback message and keeping page controls usable

## Tests

**Test cases**

- **Success**
  - S1: Authenticated user opens `/editor`, grants camera permission, and sees live webcam preview.
  - S2: With preview active, existing editor UI (stickers/controls/sidebar) remains usable.
- **Failure**
  - F1: User denies camera permission; page shows clear fallback message and no crash.
  - F2: Browser with no camera device available shows fallback message and keeps editor usable.
- **Edge**
  - E1: Browser without `getUserMedia` support shows unsupported fallback message.
  - E2: User reloads `/editor` after a deny decision; behavior remains stable (no JS errors, fallback still shown).

**Execute tests**

```bash
# 1) Start local server (adapt command/port if your project uses a different one)
php -S localhost:8080 -t public

# 2) Open app and authenticate, then navigate to /editor
# (login in browser; webcam permission requires manual browser interaction)

# 3) Verify S1/S2 manually in browser with camera permission = Allow
# 4) Verify F1 by setting camera permission = Block for localhost and reloading /editor
# 5) Verify F2 by testing in an environment/browser profile with no camera device
# 6) Verify E1 using a browser/profile where getUserMedia is unavailable

# Optional debug check for uncaught JS errors during each scenario
# Open browser DevTools Console and confirm no uncaught runtime errors
```
