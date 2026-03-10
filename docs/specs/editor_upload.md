Chosen unit: Image upload validation + temporary storage (Editor)
This is a small, self-contained subfeature of the future “Image composition” feature:
Let a logged-in user upload an image on /editor, validate that it’s a real image, and save it to a temporary uploads directory, without yet doing any sticker overlay or DB persistence.

1. Short feature specification
Behavior: On the editor page, the user selects an image file and submits; the server validates that the file is a real image (PNG/JPEG) and stores it in a temporary directory (e.g. public/tmp/), then redirects back to /editor.
Validation: Only authenticated users; accept only image MIME types; use getimagesize (or similar) to ensure the file is an actual image; enforce a max size.
Result: On success, a temporary filename/path is stored in session (e.g. $_SESSION['editor_temp_image']); on error, an error message is shown on the editor page.
2. Minimal implementation plan
Routing: Add a single endpoint, e.g. POST /editor/upload, that maps to a new method EditorController::upload().
Controller logic (EditorController::upload):
Check $_SESSION['user_id'] (same as show()); redirect to /login if missing.
Read the uploaded file from $_FILES['base_image'] and pass it (path, size, MIME) plus user id to a service.
Handle the service result: on success, store the returned temp filename in session and redirect to /editor; on error, re-render /editor with $errors['upload'].
Service logic (new ImageUploadService or similar):
Validate file presence, size, and that getimagesize succeeds and MIME is allowed.
Generate a safe, unique temp filename; move the uploaded file into public/tmp/.
Return either the temp filename (success) or an array of error messages.
View adjustment (editor.php):
Wrap the existing upload input in a form posting to /editor/upload with enctype="multipart/form-data".
If $errors['upload'] is set, display it near the upload control.
3. Controllers/services involved
Controller:

Existing EditorController::show() (unchanged except maybe reading any upload error / temp image from session).
New EditorController::upload() to handle POST /editor/upload.
Services:

New ImageUploadService (name up to you), responsible only for validating and moving the uploaded image file.
Existing StickerService is not required for this unit (keeps the unit independent and minimal).

## Implementation Plan

1. Add route `POST /editor/upload` → `EditorController::upload()`
2. In `upload()`: require auth; redirect to `/login` if not logged in
3. Create `ImageUploadService`: validate file (presence, size, MIME, getimagesize); move to `public/tmp/` with unique filename; return filename or errors
4. In `upload()`: call service; on success store temp filename in session and redirect to `/editor`; on error set `$errors['upload']` and re-render editor view
5. In `editor.php`: wrap upload control in form `POST /editor/upload`, `enctype="multipart/form-data"`, input `name="base_image"`
6. In `editor.php`: display `$errors['upload']` when set

## Tests

**Success**
- Logged-in user POSTs valid PNG → 302 to `/editor`, `$_SESSION['editor_temp_image']` set, file in `public/tmp/`
- Logged-in user POSTs valid JPEG → same

**Failure**
- POST `/editor/upload` without session → 302 to `/login`
- No file in request → editor re-rendered with `$errors['upload']`
- Non-image file (e.g. .txt, .pdf) → editor re-rendered with error
- File over max size → editor re-rendered with error
- Corrupt or fake image (getimagesize fails) → editor re-rendered with error

**Edge**
- Empty file (0 bytes) → error
- Valid image with unexpected MIME in `$_FILES` → validation via getimagesize / content, accept if content is valid image