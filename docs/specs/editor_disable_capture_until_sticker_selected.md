# Feature: `editor_disable_capture_until_sticker_selected`

Tracks [GitHub #57](https://github.com/tomjoy75/camagru_php/issues/57). Builds on [#56](https://github.com/tomjoy75/camagru_php/issues/56) / `docs/specs/editor_sticker_selection_before_base_image.md` (sticker choice before base). **Out of scope here:** upload-vs-capture policy (**#58**); server-side rejection of capture without sticker (this issue is **client UX** for the Capture control unless you explicitly extend the spec).

## Goal

Meet subject **V.4**: the control that **takes the picture from the webcam** must stay **inactive** until the user has selected a **superposable image** (sticker). Today Capture can be used before any sticker is chosen; this feature enforces the intended order **in the no-base / webcam preview state** when stickers are available.

## Behavior

- **When the editor shows the webcam placeholder** (no temp base preview) **and** at least one sticker exists: the **Capture** button (`#editor-capture-button`) is **disabled** by default and clearly non-actionable (e.g. `disabled` + existing or matching disabled styling).
- **When the user selects a sticker** in the no-base picker (`#editor-no-base-sticker-picks` / `editor_sticker_pick_no_base.js`), **enable** Capture so a framed capture can proceed and the form still submits **`sticker`** with the capture POST as today.
- **If a valid pending sticker is already present** when the page loads (e.g. non-empty `#editor-capture-sticker` / session default from #56), Capture should be **enabled** without forcing another click.
- **When stickers are unavailable** (`count($stickers) === 0`): do **not** introduce a dead-end; Capture follows **existing** `editor_webcam_preview.js` rules only (no new hard requirement to pick a missing sticker).
- **When Capture is already disabled for other reasons** (no `getUserMedia`, permission denied, no video element because a base image is shown, stream not ready): **keep** those behaviors; sticker gating must **not** re-enable Capture in those failure modes.
- **Upload** is **unchanged** by this issue (still available per **#58** unless that issue says otherwise).

## Constraints

- **Vanilla JavaScript only**; no new libraries.
- Prefer **small, explicit wiring** between `editor_webcam_preview.js` and the no-base sticker flow (e.g. custom event, shared listener on `#editor-capture-sticker` input, or minimal coordination object)—avoid duplicating sticker-validation logic (server remains source of truth for allowed filenames).
- **Accessibility:** disabled Capture should remain keyboard- and screen-reader sensible (`disabled` on the button is sufficient; avoid relying only on opacity).
- **MVC:** no business rule migration into PHP for this slice unless you add a separate spec for server-side enforcement.

## Success Criteria

- With stickers and no base image, **Capture is disabled** until the user clicks a sticker; after a pick, **Capture becomes enabled** (and remains disabled if the webcam path is unusable per existing script).
- After capture, the **existing** success path still applies and the chosen sticker is still reflected server-side as in #56.
- With **no stickers**, the editor does not trap the user: capture/upload usability matches the prior baseline for that case.
- **Regression:** webcam fallback messages, stream errors, and the “uploaded preview shown” path still disable or block capture appropriately; no new console errors on a normal `/editor` load.

## Implementation Plan

1 `editor.php`: when no base preview, stickers exist, and `editorStickerDefault` is empty, set `disabled` on `#editor-capture-button` for first paint
2 `editor_webcam_preview.js`: detect sticker gate when `#editor-no-base-sticker-picks` exists; add `syncCaptureEnabled()` so Capture is enabled only if webcam path allows capture and (not gated or `#editor-capture-sticker` has non-empty trimmed value)
3 `editor_webcam_preview.js`: refactor stream success and `disableCapture` to set a `webcamCaptureAllowed` (or equivalent) flag and end in `syncCaptureEnabled()` instead of only toggling the button
4 `editor_sticker_pick_no_base.js`: after updating sticker hiddens (including initial `setPressed`), dispatch `editor-capture-sticker-changed` on `document`
5 `editor_webcam_preview.js`: listen for `editor-capture-sticker-changed` and run `syncCaptureEnabled()` on init after DOM references exist

## Tests

**Test cases**

- **Success**
  - **S1:** Authenticated **`GET /editor`** with **no** temp workspace, **stickers present**, **empty** `#editor-capture-sticker` → HTML includes **`editor-no-base-sticker-picks`** and **`#editor-capture-button`** has the **`disabled`** attribute (first paint / progressive enhancement).
  - **S2:** Same page, after **clicking a sticker** in the no-base picker → **`#editor-capture-button`** is **enabled** (not `disabled`) while webcam is usable; **`#editor-capture-sticker`** matches the pick; submit still sends **`sticker`** with capture.
  - **S3:** If **`#editor-capture-sticker`** is **non-empty on load** (e.g. session default in a dev scenario) and sticker gate applies → Capture starts **enabled** without an extra pick.
- **Failure**
  - **F1:** Webcam **unavailable** or **blocked** (existing `disableCapture` paths) → Capture stays **disabled** even after a sticker pick; no sticker-only override.
  - **F2:** **Upload** control remains **usable** when Capture is disabled for sticker gate.
- **Edge**
  - **E1:** **`count(stickers) === 0`** (“No stickers available.”) → **no** `#editor-no-base-sticker-picks`; **`#editor-capture-button`** is **not** sticker-gated (no `disabled` **only** for missing sticker — may still be disabled for webcam reasons in the browser).
  - **E2:** Editor with **base preview** (temp image) → existing webcam script path still applies; **no** sticker gate regression on compose / save / reset.
  - **E3:** **Console** clean on normal `/editor` load (no new JS errors).

**Test Setup (authentication)**

```bash
rm -f cookies.txt
BASE=http://127.0.0.1:8080
STAMP=$(date +%s)
EMAIL="editor57_${STAMP}@example.com"
USER="editor57_${STAMP}"
PASS='Testpass1!'

curl -sS -o /dev/null -w "register %{http_code}\n" -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"
sqlite3 "${DATABASE_PATH:-database/camagru.db}" "UPDATE users SET email_verified = 1 WHERE email='$EMAIL';" 2>/dev/null || true
curl -sS -o /dev/null -w "login %{http_code}\n" -c cookies.txt -X POST "$BASE/login" \
  -d "username=$USER" -d "password=$PASS"

export BASE USER PASS
```

**Execute tests**

```bash
BASE=${BASE:-http://127.0.0.1:8080}
: "${USER:?run Test Setup first}"

# S1 — no-base + stickers: gated first paint (after feature: capture button disabled, picker present)
curl -sS -b cookies.txt "$BASE/editor" -o /tmp/editor57_s1.html
if grep -q 'id="editor-no-base-sticker-picks"' /tmp/editor57_s1.html; then
  if grep -o 'id="editor-capture-button"[^>]*' /tmp/editor57_s1.html | grep -q 'disabled'; then
    echo "S1 OK: capture button disabled in markup"
  else
    echo "S1 CHECK: expect disabled on #editor-capture-button when gate active and sticker empty"
  fi
else
  echo "S1 skip: no sticker picker in HTML (empty stickers dir?)"
fi

# E1 — cannot force zero stickers via curl alone; optional: empty public/stickers in a throwaway env, then
# curl -sS -b cookies.txt "$BASE/editor" | grep -q 'No stickers available' && ! grep -q 'editor-no-base-sticker-picks' && echo "E1 OK (no picker branch)"
```

**Manual / browser**

1. **S2 / F1 / E3:** Open `/editor` (logged in), no base, stickers visible: confirm Capture **disabled** → pick sticker → Capture **enabled** (if camera allowed); deny camera → Capture stays **disabled** after pick; check **Console** for errors.
2. **S3:** If you can set `editor_pending_sticker` (or equivalent) with no temp image in a dev session, reload `/editor` and confirm Capture **enabled** when default sticker is present.
3. **E2:** Upload or capture to get base preview; confirm compose flow and Capture/upload/reset behavior unchanged vs baseline.
