# Feature: `editor_state_based_guidance_messages`

## Goal

Add short, contextual guidance text for each canonical Editor UI state so users see what to do next across entry, composition, and save phases, without changing layout or control placement.

## Behavior

- A single guidance area (or existing equivalent) shows one short message at a time, driven by the same canonical state model as `docs/specs/editor_ui_flow.md` (and current client `editorState` / UI sync).
- Message copy per state:
  - `EMPTY` → `Select a sticker to start`
  - `EMPTY with sticker selected` → `Capture or upload a base image`
  - `BASE_READY` → `Position your sticker, then apply it`
  - `COMPOSED_READY` → `You can now save or add another sticker`
- When the editor state transitions (e.g. sticker selected, base captured/uploaded, compose applied), the message updates immediately and consistently with that transition.
- Existing controls, regions, and overall layout structure stay as today; no new UI components beyond text in an existing pattern.

## Constraints

- **Frontend only:** no new routes, controller changes, or persistence for this feature.
- **Copy scope:** only the four strings above for the listed states; no global copy rewrite.
- **Security / views:** if any message is rendered server-side from dynamic data later, escape user content per project rules; for static strings this is a non-issue.
- **Consistency:** guidance must not contradict `editor_ui_flow` or state-driven enablement rules from related specs.

## Success Criteria

- Each listed canonical state shows exactly the intended guidance string when the editor is in that state.
- State transitions (sticker select/deselect, base ready, after successful apply toward composed-ready, reset where applicable) produce the expected message changes without stale text.
- No layout redesign: spacing and control positions remain acceptable compared to before the change.
- All existing controls remain present and behave as before; this feature only adds/updates explanatory text.

## References

- GitHub: [Issue #68](https://github.com/tomjoy75/camagru_php/issues/68) — [Editor] Add state-based guidance messages
- `docs/specs/editor_ui_flow.md` — canonical states and actions

## Implementation Plan

1 identify the existing editor guidance text render location and current state source in `src/views/editor.php`
2 add a single state-to-message mapping for `EMPTY`, `EMPTY with sticker selected`, `BASE_READY`, and `COMPOSED_READY`
3 wire initial guidance text rendering from current `editorState` on first page load
4 update the state-change UI sync path so guidance text refreshes on each editor state transition
5 verify reset and sticker selection transitions do not leave stale guidance text

## Tests

**Test cases**

- **Success:** `EMPTY` shows `Select a sticker to start` on first load.
- **Success:** selecting a sticker in `EMPTY` updates guidance to `Capture or upload a base image`.
- **Success:** after capture/upload reaches `BASE_READY`, guidance shows `Position your sticker, then apply it`.
- **Success:** after successful apply reaches `COMPOSED_READY`, guidance shows `You can now save or add another sticker`.
- **Failure:** guidance does not lag behind state changes (no stale previous message after sticker deselect/reset/base replacement).
- **Edge:** repeated transitions between `EMPTY` and `EMPTY with sticker selected` always keep correct message.
- **Edge:** repeated compose cycles (`BASE_READY` -> `COMPOSED_READY` -> add another sticker/apply) keep message consistent.

**Execute tests**

```bash
set -euo pipefail

# 1) Start app locally from repository root
php -S 127.0.0.1:8080 public/index.php

# 2) In browser, open:
#    http://127.0.0.1:8080/editor
#    (login first if your app requires authentication)
#
# 3) Verify in order:
#    - Initial EMPTY message
#    - Select sticker -> EMPTY with sticker selected message
#    - Capture/upload base -> BASE_READY message
#    - Apply sticker -> COMPOSED_READY message
#    - Reset / reselect / reupload transitions -> no stale messages
```
