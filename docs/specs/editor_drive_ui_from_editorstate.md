# Feature: `editor_drive_ui_from_editorstate`

## Goal

Make canonical `editorState` the primary workflow truth for the Editor UI in `src/views/editor.php`.

This feature realigns the main Editor view branches with the canonical server/editor state model so the rendered UI follows one workflow source of truth instead of inferring workflow from preview URLs, save flags, or DOM shape.

## Behavior

`src/views/editor.php` uses canonical `editorState` as the main workflow truth for its primary UI branches.

Canonical server/editor states for this feature remain:
- `EMPTY`
- `BASE_READY`
- `COMPOSED_READY`

This feature does not introduce a new server state for `EMPTY with sticker selected`.

While `editorState === EMPTY`, the UI may still distinguish the visible substate `EMPTY with sticker selected`, but only as a local UI condition derived from current sticker-selection data.

The view must no longer drive its main workflow branching from:
- `editorPreviewSrc`
- `canSaveEditorImage`
- DOM-shape assumptions such as which preview elements or scripts are present

`editorPreviewSrc` remains display/support data for rendering the current image when one exists. It may support image rendering details, but it is not the workflow source of truth.

`canSaveEditorImage` may still be used for save visibility only if it is already aligned with canonical workflow behavior. It must not act as a competing workflow truth alongside `editorState`.

This feature realigns the main UI branches only. It does not implement later UX refinements already split into follow-up issues.

## Constraints

- Do not redesign the Editor layout.
- Do not change backend state logic.
- Do not introduce a new canonical server/editor state for `EMPTY with sticker selected`.
- Keep canonical workflow modeling limited to `EMPTY`, `BASE_READY`, and `COMPOSED_READY`.
- Keep sticker selection as local UI data, not canonical workflow state.
- Do not absorb:
- `#66` sticker-first entry gating
- `#67` auto-show selected sticker overlay
- `#68` guidance messages
- `#69` disable `Apply sticker` when no sticker selected
- Do not mix old and new workflow truth in the same implementation. Once this feature is applied, main workflow branches in `editor.php` must read from `editorState`, while preview URLs, save flags, and local sticker-selection signals remain support data only.

## Success Criteria

- `src/views/editor.php` uses canonical `editorState` to decide the main workflow branches for the Editor UI.
- The preview area, sticker-area mode, reset visibility, and similar top-level workflow branches are no longer keyed primarily off `editorPreviewSrc` or equivalent proxy conditions.
- `editorPreviewSrc` is used only to render or support the displayed image, not to determine workflow state.
- The UI may still show the local substate `EMPTY with sticker selected` while `editorState === EMPTY`, using sticker-selection data only.
- `canSaveEditorImage` does not function as a second workflow state system competing with `editorState`.
- The implementation remains limited to workflow-truth realignment and does not include the separate behavior changes reserved for `#66`, `#67`, `#68`, or `#69`.

## Implementation Plan

1. Add a local view-level mapping in `editor.php` from canonical `editorState` to the main workflow branches used by the template.
2. Switch the preview-area branch so `editorState` decides whether the Editor is in empty-workspace mode or image-workspace mode, while `editorPreviewSrc` remains only the image display source.
3. Switch the sticker-panel branch so `editorState` decides between empty-state sticker picking and base/composed sticker placement, while sticker selection remains local UI support only.
4. Switch reset visibility and script loading conditions to the same `editorState`-driven workflow mapping, removing DOM-shape and preview-presence assumptions as workflow truth.
5. Keep save visibility aligned with the canonical workflow target without introducing a second state system, and remove any remaining top-level workflow branching in `editor.php` that still depends on legacy proxies.

## Tests

### Test cases

Success cases:
- `EMPTY` renders the entry flow: webcam/entry area is shown, no workspace compose flow is shown, and no save action is shown.
- `EMPTY` with a selected sticker remains an `EMPTY` UI substate only: entry flow still renders, selected sticker can be reflected locally, and no workspace/save flow appears.
- `BASE_READY` renders workspace image and compose flow, shows reset, and does not render save.
- `COMPOSED_READY` renders workspace image, compose flow, reset, and save.
- Script loading follows workflow state: empty-flow script loads only for `EMPTY`; placement script loads only for workspace states that render compose UI.

Failure / regression checks:
- No top-level branch in `editor.php` still switches workflow mode primarily from `editorPreviewSrc`.
- `canSaveEditorImage` does not make save appear in `EMPTY` or `BASE_READY`.
- Reset does not appear in `EMPTY`, even if display/support variables are present unexpectedly.
- Workspace/compose markup does not appear in `EMPTY` just because preview-related display data exists.

Edge cases:
- `EMPTY` with no selected sticker and `EMPTY` with selected sticker both keep the same canonical workflow state.
- Workspace states with missing or partial display-support data do not fall back to the wrong workflow branch.
- No-stickers rendering still follows the correct workflow state without changing the canonical branch model.

### Required manual coverage

- `EMPTY` renders entry flow, not workspace flow.
- `EMPTY` with sticker selected remains a UI substate only.
- `BASE_READY` renders workspace + compose flow, but not save.
- `COMPOSED_READY` renders workspace + save.
- `Reset` visibility aligns with non-empty states.
- Script loading / branch loading aligns with `editorState`.
- No visible workflow branch still depends primarily on `editorPreviewSrc` as workflow truth.

### Manual verification checklist

1. Open the Editor in `EMPTY` and confirm the entry flow is visible, workspace compose UI is absent, `Save image` is absent, and `Reset` is absent.
2. While still in `EMPTY`, select a sticker and confirm the UI only reflects local sticker selection; the canonical flow remains entry-only and no workspace/save branch appears.
3. Reach `BASE_READY` and confirm the workspace image and compose UI render, `Reset` is visible, and `Save image` is still not rendered.
4. Reach `COMPOSED_READY` and confirm the workspace image, compose UI, `Reset`, and `Save image` all render together.
5. Move from `COMPOSED_READY` back to `EMPTY` or `BASE_READY` through normal flow transitions and confirm `Save image` and `Reset` visibility update with `editorState`, not with stale preview/support data.
6. Inspect the rendered page for each state and confirm the empty-flow script is only present for `EMPTY`, while the placement script is only present when the workspace compose branch is active.
7. Review the final `editor.php` branching and confirm no visible workflow branch is keyed primarily from `editorPreviewSrc`, `canSaveEditorImage`, or DOM-shape assumptions.
