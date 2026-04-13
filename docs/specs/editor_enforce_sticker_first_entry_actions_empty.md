# Feature: `editor_enforce_sticker_first_entry_actions_empty`

Tracks [GitHub #66](https://github.com/tomjoy75/camagru_php/issues/66). Builds on the canonical flow in `docs/specs/editor_ui_flow.md` and on earlier sticker-first entry work from [#56](https://github.com/tomjoy75/camagru_php/issues/56), [#57](https://github.com/tomjoy75/camagru_php/issues/57), and [#58](https://github.com/tomjoy75/camagru_php/issues/58).

## Goal

Enforce the canonical sticker-first entry rule while the Editor is in `EMPTY`: the user must choose a sticker before starting the workspace through either entry action.

This keeps capture and upload aligned, prevents invalid or misleading entry actions, and matches the intended `EMPTY` -> `EMPTY with sticker selected` -> `BASE_READY` flow.

## Behavior

- When the Editor is in canonical `EMPTY` and stickers are available:
  - `Capture` is visible but disabled until a sticker is selected.
  - selecting a file for upload does not auto-submit until a sticker is selected.
- When the Editor is still `EMPTY` but a valid sticker is selected:
  - `Capture` becomes available.
  - selecting a file triggers the existing upload auto-submit behavior.
- The feature reuses the existing hidden sticker values already maintained by the Editor UI.
- This issue is limited to local UI entry gating in `EMPTY`; it does not redefine workspace behavior after a base image exists.

## Constraints

- Keep the implementation front-end only for this slice; no controller, service, or persistence change is required.
- Reuse the existing `editorState`-driven flow and current no-base sticker picker wiring.
- Do not redesign the Editor layout.
- Do not change behavior in `BASE_READY` or `COMPOSED_READY`.
- Do not absorb follow-up issues:
  - `#67` auto-show selected sticker overlay in `BASE_READY`
  - `#68` state-based guidance messages
  - `#69` disable `Apply sticker` when no sticker is selected
- If no stickers are available, keep the current non-gated behavior rather than creating a dead-end.

## Success Criteria

1. In `EMPTY` with no sticker selected, `Capture` is visibly disabled.
2. In `EMPTY` with no sticker selected, selecting a file does not trigger upload submission.
3. In `EMPTY` with a selected sticker, `Capture` becomes available.
4. In `EMPTY` with a selected sticker, selecting a file triggers the normal upload auto-submit flow.
5. The change remains limited to entry gating in `EMPTY`, with no regression to non-empty editor states and no backend change required.

## Implementation Plan

1. in `editor.php`, align the `EMPTY` view branch to one empty-state boolean (`$isEmptyState`) and compute the no-sticker entry gate from `editorState`, sticker availability, and the existing hidden sticker values
2. in `editor.php`, render `Capture` disabled on first paint when the `EMPTY` no-sticker gate is active and keep non-empty states unchanged
3. in `public/js/editor_webcam_preview.js`, reuse and refine the existing capture gating so `Capture` enables only when webcam capture is allowed and the no-base sticker hidden value is non-empty
4. in `public/js/editor_upload_autosubmit.js`, reuse and refine the existing `EMPTY` upload guard so file selection does not auto-submit until the upload sticker hidden value is non-empty
5. in `public/js/editor_sticker_pick_no_base.js`, keep the script limited to no-base sticker selection, hidden field sync, pressed-state updates, and event dispatch for the other scripts to react to

## Tests

**Test cases**

- **Success**
  - **S1:** Authenticated `GET /editor` in canonical `EMPTY`, with stickers present and no selected sticker, shows the no-base sticker picker and renders `#editor-capture-button` as `disabled`.
  - **S2:** In the browser, while still in `EMPTY`, selecting a sticker enables `Capture` and allows the existing upload auto-submit path.
- **Failure**
  - **F1:** In the browser, while in `EMPTY` with no selected sticker, choosing a file does not submit the upload form and does not create a workspace base image.
  - **F2:** When webcam capture is unavailable for existing reasons, selecting a sticker does not incorrectly force `Capture` enabled.
- **Edge**
  - **E1:** With no stickers configured, the page does not create a dead-end or JavaScript error; sticker-first gating is not applied.
  - **E2:** In `BASE_READY` or `COMPOSED_READY`, existing upload / compose / save / reset behavior remains unchanged by this feature.

**Test Setup (authentication)**

```bash
rm -f cookies.txt
BASE=http://127.0.0.1:8080
STAMP=$(date +%s)
EMAIL="editor66_${STAMP}@example.com"
USER="editor66_${STAMP}"
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

# Reset to empty workspace before markup checks
curl -sS -o /dev/null -b cookies.txt -X POST "$BASE/editor/reset"

# S1 — empty workspace + stickers: capture starts disabled on first paint
curl -sS -b cookies.txt "$BASE/editor" -o /tmp/editor66_s1.html
if grep -q 'id="editor-no-base-sticker-picks"' /tmp/editor66_s1.html; then
  if grep -o 'id="editor-capture-button"[^>]*' /tmp/editor66_s1.html | grep -q 'disabled'; then
    echo "S1 OK: capture disabled in EMPTY without sticker"
  else
    echo "S1 CHECK: expected disabled on #editor-capture-button when gate is active"
  fi
else
  echo "S1 skip: no sticker picker in HTML (empty stickers dir?)"
fi

# E1 — optional throwaway-env check when stickers are removed:
# curl -sS -b cookies.txt "$BASE/editor" | grep -q 'No stickers available' && echo "E1 OK"
```

**Manual / browser**

1. **S2 / F1:** Open `/editor` logged in with an empty workspace and stickers visible. Confirm `Capture` is disabled. Choose a file before selecting a sticker and confirm no upload starts and no workspace image appears. Then click a sticker, choose a file again, and confirm upload proceeds normally.
2. **F2:** Reload `/editor`, block camera permission or use an environment where capture is already unavailable, select a sticker, and confirm `Capture` remains unavailable for the pre-existing webcam reason.
3. **E2:** Reach `BASE_READY` and `COMPOSED_READY` through normal flow, then confirm upload replacement, compose, save, and reset still behave like before.

