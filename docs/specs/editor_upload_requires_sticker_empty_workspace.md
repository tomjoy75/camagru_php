# Feature: `editor_upload_requires_sticker_empty_workspace`

Tracks [GitHub #58](https://github.com/tomjoy75/camagru_php/issues/58). Complements [#56](https://github.com/tomjoy75/camagru_php/issues/56) (sticker choice before base) and [#57](https://github.com/tomjoy75/camagru_php/issues/57) (capture gated until sticker): **upload** must follow the same “sticker before creating a base” rule when the workspace is still empty.

## Goal

Align **POST `/editor/upload`** with **capture** for the **no-base** editor state: the user must **not** be able to submit an upload that establishes a workspace temp image until a **sticker is selected** in the UI (same intent as capture). This removes the asymmetry where a base could be created via upload without the same sticker-first flow.

## Behavior

- **When there is no workspace base image** (`editorPreviewSrc` empty / no temp image in session for the editor workspace) **and** at least one sticker exists: the **upload form’s submit control** (`Upload` button on `#editor-upload-form`) is **disabled** until the user selects a sticker via the existing no-base sticker picks (`editor-no-base-sticker-picks` / `editor_sticker_pick_no_base.js`). After a pick, the hidden `sticker` field is non-empty and the submit control becomes **enabled**, consistent with capture for that state.
- **When no stickers are configured:** keep current behavior (no sticker row to pick from); this feature does not add new requirements beyond the issue acceptance wording (“stickers available”).
- **When a base image is already loaded:** do **not** block upload submit solely for missing a fresh sticker pick (replace / supersede flows must keep working; document in implementation notes if “replace base” behavior differs).
- **Optional (recommended):** in `EditorController::upload()`, if the workspace is **empty** before accepting this upload, require a **valid** `sticker` in `$_POST` against the same allow list used elsewhere (`StickerService` / compose rules). On violation, reject with a safe redirect and user-visible error (no **500**), same spirit as other editor validations. Do not weaken validation on compose.

## Constraints

- **PHP standard library only**; **no** new front-end frameworks.
- **MVC:** views/HTML and small vanilla JS only for UX gating; any new rule in `upload()` lives in the controller (calling existing services for allow lists), not in views beyond form fields.
- **Security:** do not trust the client alone for the empty-workspace rule when server check is implemented; never treat sticker as a raw path—validate as an allowed sticker **filename** (or equivalent) only.
- **Explicitly out of scope:** blocking **Save to gallery** until a successful server compose — tracked separately ([#62](https://github.com/tomjoy75/camagru_php/issues/62)); preview vs server-applied copy — [#63](https://github.com/tomjoy75/camagru_php/issues/63).

## Success Criteria

1. With **no** workspace image and **stickers available**, the upload **submit** control is disabled until a sticker is selected.
2. After a sticker is selected (same flow as capture for that page state), upload submit becomes enabled and a normal upload still succeeds end-to-end.
3. With a base **already** loaded, upload/replace behavior shows **no** regression versus the baseline (file still accepted when appropriate).
4. This feature does **not** subsume the mandatory “save only after compose” work; it only addresses **upload vs capture parity** for sticker-before-base.

## Implementation Plan

1 add `id="editor-upload-submit"` on the upload form submit in `editor.php`
2 render that submit `disabled` with muted styles when no base preview, stickers exist, and default sticker hidden is empty
3 in `public/js/editor_sticker_pick_no_base.js` sync `#editor-upload-submit` `disabled` to empty `#editor-upload-sticker` on init and after each pick
4 in `EditorController::upload()` before `ImageUploadService::processUpload`, if session has no editor temp image, require POST `sticker` to pass `StickerService::isAllowedStickerFilename`
5 on failed step 4, set `$_SESSION['editor_error']`, redirect to `/editor`, and do not process `$_FILES`

## Tests

**Test cases**

- **Success (server):** Authenticated user, empty editor workspace, `POST /editor/upload` with valid `base_image` and `sticker=<allowed filename>` → redirect to `/editor`, workspace image updated, no **500**.
- **Success (server):** Workspace already has a temp base; `POST /editor/upload` with only `base_image` (replace flow) → still accepted; no regression.
- **Success (UI, manual):** No base + stickers present → Upload submit starts disabled, enables after a sticker pick; multipart submit completes as today.
- **Failure (server):** Empty workspace, authenticated `POST /editor/upload` with file but **no** `sticker` field (or empty value) → redirect to `/editor` with error flash; upload not applied.
- **Failure (server):** Empty workspace, `sticker` not in `StickerService` allow list (unknown name or path-like value) → same safe rejection.
- **Failure (server):** Unauthenticated `POST /editor/upload` → redirect to `/login`.
- **Edge (server):** After a successful upload, `POST /editor/reset` clears workspace; next upload again requires `sticker` until a base exists.
- **Edge (UI, manual):** No stickers configured → Upload remains usable (no sticker row); no **500** on `/editor`.

**Test Setup (authentication)**

```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies.txt"

rm -f "$COOKIE_JAR"

# Register (ignore if user already exists)
curl -sS -i -c "$COOKIE_JAR" -X POST "$BASE/register" \
  -d "email=editor.upload.sticker@example.com" \
  -d "username=editoruploadsticker" \
  -d "password=Test1234!" \
  -d "confirm_password=Test1234!" >/tmp/editor_upload_sticker_register.out

# Confirm email if required (token from dev DB or mail sink), then:
# curl -sS -c "$COOKIE_JAR" "$BASE/register/confirm?token=YOUR_TOKEN"

# Login
curl -sS -i -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/login" \
  -d "username=editoruploadsticker" \
  -d "password=Test1234!" >/tmp/editor_upload_sticker_login.out
```

**Execute tests**

```bash
BASE="http://localhost:8080"
COOKIE_JAR="cookies.txt"

# Tiny valid PNG (reuse path from other editor specs)
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQmY9WkAAAAASUVORK5CYII=' | base64 -d > /tmp/editor_upload_sticker_1x1.png

# Clear workspace before sticker-guard cases (idempotent if already empty)
curl -sS -o /dev/null -b "$COOKIE_JAR" -X POST "$BASE/editor/reset"

# S1: empty workspace + allowed sticker + file → expect 302 to /editor (Location header)
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "sticker=hat.png" \
  -F "base_image=@/tmp/editor_upload_sticker_1x1.png;type=image/png"

# S2: replace-base upload without sticker field (session has temp after S1) → expect 302 success
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/editor_upload_sticker_1x1.png;type=image/png"

# F1: empty workspace, file only, no sticker → expect 302 /editor; then GET shows error (after server guard lands)
curl -sS -o /dev/null -b "$COOKIE_JAR" -X POST "$BASE/editor/reset"
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "base_image=@/tmp/editor_upload_sticker_1x1.png;type=image/png"
curl -sS -b "$COOKIE_JAR" "$BASE/editor" | grep -E 'border-red-300|editor_error|sticker' || true

# F2: empty workspace, invalid sticker value
curl -sS -o /dev/null -b "$COOKIE_JAR" -X POST "$BASE/editor/reset"
curl -sS -i -b "$COOKIE_JAR" -X POST "$BASE/editor/upload" \
  -F "sticker=../../../etc/passwd" \
  -F "base_image=@/tmp/editor_upload_sticker_1x1.png;type=image/png"

# F3: unauthenticated upload
curl -sS -i -X POST "$BASE/editor/upload" \
  -F "sticker=hat.png" \
  -F "base_image=@/tmp/editor_upload_sticker_1x1.png;type=image/png"
```

**Notes:** Run **Execute tests** with the PHP app listening on `$BASE` (see project README / docker). **F1**/**F2** assert the sticker-empty-workspace guard once steps 4–5 of the implementation plan are merged; until then they document intended behavior. **UI** rows require a browser check on `/editor`.
