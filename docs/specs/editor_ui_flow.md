# Feature: `editor_ui_flow`

## Goal

Define the canonical Editor UI flow as the shared reference for future Editor changes.

This spec describes the expected user journey from first sticker selection to base creation, sticker application, and save. Its role is to prevent future Editor work from reintroducing ad hoc local rules or conflicting UI behavior.

## Canonical UI states

### `EMPTY`

The Editor workspace is empty and no sticker is currently selected.

User sees:
- webcam / base-creation area
- no workspace image
- no sticker placement overlay
- sticker choices for entry

Visible actions:
- `Capture`
- `Upload image`

Enabled / disabled:
- `Capture`: visible, disabled
- `Upload image`: visible, disabled
- `Apply sticker`: hidden
- `Save image`: hidden
- `Reset`: hidden

### `EMPTY with sticker selected`

The Editor workspace is still empty, but the user has selected a sticker for entry into the flow.

User sees:
- webcam / base-creation area
- no workspace image
- selected sticker indicated in the picker

Visible actions:
- `Capture`
- `Upload image`

Enabled / disabled:
- `Capture`: visible, enabled
- `Upload image`: visible, enabled
- `Apply sticker`: hidden
- `Save image`: hidden
- `Reset`: hidden

### `BASE_READY`

A base image exists in the workspace, but no server-side compose has been applied since that base was created or replaced.

User sees:
- workspace image
- sticker placement UI
- sticker overlay preview when a sticker is selected

Visible actions:
- `Capture`
- `Upload image`
- `Apply sticker`
- `Reset`

Enabled / disabled:
- `Capture`: hidden or disabled
- `Upload image`: visible, enabled
- `Apply sticker`: visible, enabled only when a sticker is selected
- `Save image`: hidden or disabled
- `Reset`: visible, enabled

### `COMPOSED_READY`

A workspace image exists and at least one valid server-side compose has been applied since the last base creation or replacement.

User sees:
- workspace image (pixels already include the last successful compose)
- sticker placement UI
- no placement overlay until the user explicitly selects a sticker for the **next** compose (the pending sticker draft is cleared server-side after each successful apply)

Visible actions:
- `Capture`
- `Upload image`
- `Apply sticker`
- `Save image`
- `Reset`

Enabled / disabled:
- `Capture`: hidden or disabled
- `Upload image`: visible, enabled
- `Apply sticker`: visible, enabled only when a sticker is selected
- `Save image`: visible, enabled
- `Reset`: visible, enabled

## Canonical transitions

- select sticker from `EMPTY` -> `EMPTY with sticker selected`
- select a different sticker from `EMPTY with sticker selected` -> `EMPTY with sticker selected`
- capture from `EMPTY with sticker selected` -> `BASE_READY`
- upload from `EMPTY with sticker selected` -> `BASE_READY`
- upload from `BASE_READY` -> `BASE_READY` (replace base)
- upload from `COMPOSED_READY` -> `BASE_READY` (replace base and lose composed-save readiness)
- apply sticker from `BASE_READY` -> `COMPOSED_READY`
- apply sticker from `COMPOSED_READY` -> `COMPOSED_READY`
- save from `COMPOSED_READY` -> `EMPTY`
- reset from `BASE_READY` -> `EMPTY`
- reset from `COMPOSED_READY` -> `EMPTY`

## UI rules / invariants

- Visible controls must not lead to silent failure for normal user actions.
- The server remains the source of truth for workspace state and final validation.
- `Save image` is valid only in `COMPOSED_READY`.
- Sticker selection before compose is preview only; it does not modify the server image by itself.
- `Apply sticker` is the user action that requests server-side compose.
- After a **successful** compose, the session’s **pending sticker draft** (`editor_pending_sticker`) must be cleared so the next `GET /editor` in `COMPOSED_READY` does not auto-restore a preview overlay on top of the already composed image; the user starts a new placement only by picking a sticker again.
- Upload creates or replaces the current base image and returns the flow to `BASE_READY`.
- Capture and upload are entry / base-replacement actions, not save-enabling actions by themselves.
- `editorState` is the workflow source of truth; image URLs are display data only.

## Control matrix

| UI state | Capture | Upload image | Apply sticker | Save image | Reset |
|---|---|---|---|---|---|
| `EMPTY` | visible, disabled | visible, disabled | hidden | hidden | hidden |
| `EMPTY with sticker selected` | visible, enabled | visible, enabled | hidden | hidden | hidden |
| `BASE_READY` | hidden or disabled | visible, enabled | visible, enabled only with sticker selected | hidden or disabled | visible, enabled |
| `COMPOSED_READY` | hidden or disabled | visible, enabled | visible, enabled only with sticker selected | visible, enabled | visible, enabled |

## Non-goals

This spec does not define:
- a modal sticker picker
- a multi-sticker workflow
- a backend refactor
- a full layout redesign
- changes to image processing rules
- a new capture flow beyond the canonical state behavior described above

## Notes for future issues

The following issues should align with this spec:
- #65 drive Editor UI behavior from `editorState`
- #66 enforce sticker-first entry actions in `EMPTY`
- #67 auto-show selected sticker overlay in `BASE_READY`
- #68 add state-based guidance messages
- #69 disable `Apply sticker` when no sticker is selected
