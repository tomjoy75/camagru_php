# Editor code map

This document maps **canonical server state** and the **four UI situations** to concrete PHP, view, and JavaScript code. Paths are relative to the repository root.

---

## Global principle

| Concern | Where it lives |
|---------|----------------|
| **Canonical workflow state** (`EMPTY` / `BASE_READY` / `COMPOSED_READY`) | `EditorController` — normalized in `getNormalizedEditorState()`, persisted in session via `setEditorWorkspaceState()` |
| **HTML structure, which forms exist, initial disabled flags** | `src/views/editor.php` — branches on `$editorState`, `$editorStickerDefault`, dimensions, flags like `$canRenderComposeForm` |
| **Sticker pick before base, live guidance tweak, placement overlay, webcam/upload UX** | `public/js/editor_*.js` — must match what the DOM contains for each response |

**Entry route:** `GET /editor` → `src/routes/index.php` (around lines 90–92) → `EditorController::show()` → layout loads `src/views/editor.php`.

---

## Canonical server-side state model

### Constants (string values)

Defined at the top of `EditorController`:

```8:11:src/controller/EditorController.php
    private const EDITOR_STATE_EMPTY = 'EMPTY';
    private const EDITOR_STATE_BASE_READY = 'BASE_READY';
    private const EDITOR_STATE_COMPOSED_READY = 'COMPOSED_READY';
```

### Session keys

| Key | Constant / literal | Role |
|-----|-------------------|------|
| `editor_workspace_state` | `EDITOR_STATE_SESSION_KEY` (line 6) | Stores `BASE_READY` or `COMPOSED_READY` when non-empty; cleared when state is `EMPTY` |
| `editor_temp_image` | (string literal in code) | Basename (or raw filename) of workspace file under `public/tmp/` |
| `editor_pending_sticker` | `PENDING_STICKER_SESSION_KEY` (lines 13–14) | Validated sticker basename for defaults / POST sync — **not** a fourth `editorState` |
| `editor_workspace_compose_ok_basename` | `COMPOSE_AUTHORIZED_BASENAME_SESSION_KEY` (lines 16–19) | After successful compose, basename allowed for **save** |

### Normalization (`EMPTY` vs `BASE_READY` vs `COMPOSED_READY`)

`getNormalizedEditorState(?string $editorTempImage)` (lines 467–479):

1. If `hasValidWorkspaceTempImage($editorTempImage)` is false → **`EMPTY`**.
2. Else if `$_SESSION['editor_workspace_state'] === 'COMPOSED_READY'` → **`COMPOSED_READY`**.
3. Else → **`BASE_READY`** (including when session workspace state is missing but the temp file exists).

So **`BASE_READY`** is the default whenever there is a **valid on-disk** workspace temp image and the session is not explicitly **`COMPOSED_READY`**.

### Where state is written

| Method | State / side effects |
|--------|----------------------|
| `upload()` (lines 36–71) | On success: `replaceEditorWorkspaceTemp`, `setEditorWorkspaceState(BASE_READY)`, `syncPendingStickerFromPost()` |
| `capture()` (lines 73–97) | Same pattern as upload on success |
| `compose()` (lines 99–165) | On success: `replaceEditorWorkspaceTemp` with composed file, sets `COMPOSE_AUTHORIZED_BASENAME_SESSION_KEY`, unsets pending sticker, `setEditorWorkspaceState(COMPOSED_READY)` |
| `save()` (lines 167–282) | Requires normalized `COMPOSED_READY` early; on success clears temp + keys, `setEditorWorkspaceState(EMPTY)` |
| `reset()` (lines 310–328) | Clears temp session, `setEditorWorkspaceState(EMPTY)`, clears pending + compose authorization |

`setEditorWorkspaceState()` (lines 481–494): **`EMPTY`** unsets `editor_workspace_state`; **`BASE_READY`** / **`COMPOSED_READY`** set the session value.

### Exposed to the view

`editorViewContext()` (lines 370–429) builds variables consumed by `editor.php`:

- `editorState` — from `getNormalizedEditorState($editorTempImage)` (lines 377–380)
- `editorTempImage`, `editorPreviewSrc`, `editorBaseNaturalW` / `editorBaseNaturalH`, `canSaveEditorImage`, `editorStickerDefault`, stickers list, flash messages, etc.

`EditorController::show()` calls `extract(self::editorViewContext(), EXTR_SKIP)` then includes the layout (lines 29–33).

**Implementation note:** the returned array lists `'editorState'` twice (lines 418–421). In PHP the **later** duplicate wins; behavior matches a single `'editorState' => $editorState`. Treat as redundant, not two different sources.

---

## UI situation 1 — EMPTY

### Meaning

`getNormalizedEditorState()` returns **`EMPTY`**: no valid workspace temp image path pointing to an existing file under `public/tmp/`.

### Route / entry

- `GET /editor` → `EditorController::show()` (`src/routes/index.php` ~90–92).

### Controller

- `show()` → `editorViewContext()` → `getNormalizedEditorState()` (lines 22–33, 370–380, 467–479).
- No workspace image: `upload()` / `capture()` enforce sticker when there is no base yet (lines 43–51, sticker gate via `StickerService::isAllowedStickerFilename`).

### View (`src/views/editor.php`)

| Lines (approx.) | Behavior |
|-----------------|----------|
| 3–10 | `$isEmptyState`, `$isWorkspaceState`, `$isBaseReadyState`, `$isComposedReadyState` derived from `$editorState` |
| 16–18 | `$editorEntryStickerGateActive`: empty state, stickers exist, **no** `editorStickerDefault` from server |
| 104–116 | **Webcam branch**: `#editor-preview-host` contains `#editor-webcam-preview` + fallback (no workspace `<img>`) |
| 168–186 | **Entry sticker picks**: `#editor-no-base-sticker-picks` when `$isEmptyState` and stickers exist but **not** the compose form |
| 197–231 | **Capture** / **upload** forms: hidden `sticker` fields; capture/upload disabled when `$editorEntryStickerGateActive` (server) until JS lifts restrictions after client sticker pick |
| 232–248 | **Save** form only if `$isComposedReadyState` (false here). **Reset** only if `$isWorkspaceState` (false here) |

### JavaScript

| File | Role |
|------|------|
| `public/js/editor_webcam_preview.js` | Webcam stream, fills `#editor-capture-input` on submit; syncs **Capture** enabled when sticker gate element exists and hidden sticker non-empty (lines 22–34, 49–50) |
| `public/js/editor_upload_autosubmit.js` | When `data-editor-state` on `#editor-upload-form` is `EMPTY`, disables file input until `#editor-upload-sticker` non-empty (lines 17–38, 49–50) |
| `public/js/editor_sticker_pick_no_base.js` | Loaded only if `$isEmptyState && count($stickers) > 0` (view lines 288–290). Handles `#editor-no-base-sticker-picks`, syncs `#editor-capture-sticker` / `#editor-upload-sticker`, toggles guidance text for EMPTY vs “with sticker” (lines 26–39, 42–51) |

### Visible controls (typical)

- Sticker grid (entry picks), **Capture** (often disabled until sticker), **Upload** (disabled until sticker), guidance message. **Apply sticker**, **Save**, **Reset** are not shown for this server state (compose form requires workspace state + dimensions + stickers).

---

## UI situation 2 — EMPTY with sticker selected

### Why this is not a new server state

`$editorState` from the server is still **`EMPTY`**. The `#editor-guidance-message` element keeps `data-editor-state="EMPTY"` (see view lines 56–59). The “with sticker” wording is either:

- **Client-only:** `editor_sticker_pick_no_base.js` reads hidden `#editor-capture-sticker` / `#editor-upload-sticker` and swaps text using `data-empty-with-sticker-message` (lines 26–39 in that JS file), **or**
- **After a redirect with session default:** if `editor_pending_sticker` is set (only after successful capture/upload in previous flows, then user reset temp… actually **reset** clears pending sticker — line 320). So for a **fresh** EMPTY page, `editorStickerDefault` is usually empty until the user picks a sticker in JS.

**Important:** picking a sticker in the empty workspace does **not** call `setEditorWorkspaceState()`; it does not set `editor_temp_image`. Server normalization unchanged until **POST** capture/upload.

### JavaScript / hidden inputs

| Element | Role |
|---------|------|
| `#editor-capture-sticker` | Posted with `POST /editor/capture` as `sticker` |
| `#editor-upload-sticker` | Posted with `POST /editor/upload` as `sticker` |
| `#editor-guidance-message` | `data-editor-state`, `data-empty-message`, `data-empty-with-sticker-message` — JS updates visible guidance without reload |

### View interaction

Same PHP branch as **EMPTY**; server may still compute `$editorEntryStickerGateActive` true on first paint if `editorStickerDefault` is empty — JS then enables capture/upload when the user selects a sticker (see `editor_webcam_preview.js` / `editor_upload_autosubmit.js`).

---

## UI situation 3 — BASE_READY

### How the server enters this state

- Successful **`upload()`** or **`capture()`**: `setEditorWorkspaceState(self::EDITOR_STATE_BASE_READY)` (lines 61, 87).
- **`compose()`** failure keeps existing temp; state depends on prior session (often still `BASE_READY` if never composed).
- Replacing the base via upload/capture from **`COMPOSED_READY`**: `replaceEditorWorkspaceTemp` clears compose authorization (lines 333–335); then `setEditorWorkspaceState(BASE_READY)` on success — user must compose again before save.

### View branches (`editor.php`)

| Condition | Effect |
|-----------|--------|
| `$isWorkspaceState` true (lines 8–9) | Workspace preview host with `#editor-base-preview-img` when `$canRenderWorkspaceImage` |
| `$canRenderComposeForm` (lines 12–15) | `BASE_READY` or `COMPOSED_READY` **and** natural width/height set **and** stickers exist → `#editor-compose-form` to `POST /editor/compose` with hidden `sticker`, `x`, `y`, `scale`, `angle` |
| `$isWorkspaceState` | **Reset** form to `POST /editor/reset` (lines 242–247) |
| Capture / upload forms | Still rendered; webcam DOM absent in workspace branch — `editor_webcam_preview.js` disables capture when `#editor-webcam-preview` missing (lines 44–47) |

### Controls

- Sticker placement UI, **Apply sticker** (disabled server-side when `editorStickerDefault` empty — lines 156–161; JS in `editor_sticker_placement.js` also syncs disabled state).
- **Upload** remains available to replace the base.
- **Save** not rendered (`$isComposedReadyState` false); `canSaveEditorImage` is false in context (line 404).

### JavaScript

| File | Role |
|------|------|
| `public/js/editor_sticker_placement.js` | Loaded when `$canRenderComposeForm` (view lines 291–293). Drag/scale/rotate, fills compose hiddens, optional entry auto-show from `data-entry-autoshow` / `data-entry-sticker` on `#editor-sticker-stage` (view lines 87–89, JS lines 227–251) |
| `public/js/editor_upload_autosubmit.js` | For non-`EMPTY` `data-editor-state`, upload not gated by entry sticker (lines 17–19, 25–38) |

---

## UI situation 4 — COMPOSED_READY

### How `compose()` transitions here

On successful `ImageComposeService::compose()` (lines 149–161): workspace temp replaced by composed output basename, `editor_workspace_compose_ok_basename` set to that basename when valid, `editor_pending_sticker` cleared, `setEditorWorkspaceState(COMPOSED_READY)`, redirect `GET /editor`.

### Why save is allowed

1. **`EditorController::save()`** (lines 174–179) rejects unless `getNormalizedEditorState(...)` is **`COMPOSED_READY`**.
2. Even then, **`isEditorWorkspaceComposeAuthorizedForSessionTemp()`** (lines 216–223, helper 453–465) must match the current temp basename to the post-compose authorized basename — prevents saving an uncomposed upload.

### View

- Same compose form branch as `BASE_READY` when dimensions and stickers exist.
- **Save** form appears when `$isComposedReadyState` (lines 232–237).

### Authoritative image vs local preview

- **Authoritative workspace pixels** after compose: the file under `public/tmp/` referenced by `editor_temp_image` (server-side GD output). `editorPreviewSrc` is `/tmp/{basename}` for display (`editorViewContext` lines 385–395).
- **Local preview** (`editor_sticker_placement.js`): overlay and placement are for **drafting the next** compose; they do not change the saved file until the user submits **Apply sticker** again.

---

## Support data vs workflow truth

| Variable / mechanism | Role |
|---------------------|------|
| **`editorState`** (`EMPTY` / `BASE_READY` / `COMPOSED_READY`) | **Workflow truth** — drives save eligibility, which major UI branch renders, and normalized from session + temp file validity |
| **`editorPreviewSrc`** | **Display support only** — URL for `<img src>`; absent if file missing or invalid |
| **`editorBaseNaturalW` / `editorBaseNaturalH`** | **Technical support** — from `getimagesize()` for compose form and client placement math; if missing, `$canRenderComposeForm` is false (lines 12–15) |
| **`editor_pending_sticker` / `editorStickerDefault`** | **Defaults and POST sync** — not a fourth state; cleared on successful compose (line 158) |
| **Hidden fields + JS in EMPTY** | **Local UI support** — sticker choice before first base image reaches the server |

---

## Short oral explanation (French)

L’éditeur Camagru sépare clairement **l’état métier côté serveur** et **ce que le navigateur affiche**. Le serveur ne connaît que **trois états** : `EMPTY` sans image temporaire valide, `BASE_READY` quand une image de travail existe dans `public/tmp` mais qu’aucune composition serveur n’a encore validé l’étape « prêt à enregistrer », et `COMPOSED_READY` après un **POST /editor/compose** réussi. Côté interface, on voit parfois **quatre situations** : les trois précédentes plus le cas « toujours EMPTY mais un sticker est déjà choisi » avant capture ou upload — ce n’est **pas** un quatrième état PHP, seulement des champs cachés et du JavaScript qui préparent le prochain formulaire. La **vérité** du flux reste dans `EditorController` (session + fichier temporaire) ; l’image affichée et les dimensions servent au rendu et au placement ; **enregistrer** n’est autorisé que si l’état normalisé est `COMPOSED_READY` **et** si une clé de session atteste qu’une composition serveur a bien produit le fichier courant.

---

## Spec cross-reference

Product-oriented UI state names and transitions are summarized in `docs/specs/editor_ui_flow.md` (canonical UI states and transitions). This code map ties those names to `EditorController`, `editor.php`, and `public/js/editor_*.js`.
