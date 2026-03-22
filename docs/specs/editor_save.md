# Feature: Editor save (persist composed image)

## Goal

Let a logged-in user save the current editor image from `public/tmp/` to durable storage, record it in the `images` table, and see recent saved images in the editor sidebar. No public gallery, no delete, no sticker/position metadata in this unit.

## Behavior

1. **POST `/editor/save`**
2. Require authenticated user; otherwise redirect to `/login`.
3. Require `$_SESSION['editor_temp_image']` non-empty; the file must exist under `public/tmp/` (basename only).
4. Validate filename strictly: `img_` + 16 lowercase hex + `.png` or `.jpg` (matches upload/compose naming).
5. **Move** (rename) `public/tmp/<name>` → `public/uploads/<name>` (create `public/uploads` if needed).
6. **INSERT** into `images` (`user_id`, `image_path`) with `image_path` = `uploads/<name>` (relative URL path).
7. On DB failure: move file back to `public/tmp/` if possible; show error on editor.
8. On success: **302** to `GET /editor`.
9. **GET `/editor`**: load recent images for the user (e.g. last 12), pass to the view; sidebar shows thumbnails linking to `/uploads/...` or `/tmp/...` as stored in DB (`uploads/...` only for saved rows).

## Preview after save

The session still holds the same basename. The main preview resolves: if file exists in `tmp`, use `/tmp/...`; else if in `uploads`, use `/uploads/...`.

## Routes

- `POST /editor/save` → `EditorController::save()`
- `GET /uploads/<file>` → same safety pattern as `/tmp/` (basename, allowed extensions, file under `public/uploads/`)

## Constraints

- Do not trust client-provided filenames for save; only session value after validation.
- No path traversal (`basename` + strict regex).

## Tests (curl)

After login and upload + optional compose:

```bash
curl -s -o /dev/null -w "%{http_code}" -b cookies.txt -X POST "$BASE/editor/save"
# Expect: 302
```

Without session: expect 302 to `/login`. Without temp file: expect 200 with save error on editor.
## Post-feature cleanup (optional)

- Reduce verbosity in EditorController
- Replace extract(...) with explicit variable assignment if needed
