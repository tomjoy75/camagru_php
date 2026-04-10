# Feature: `editor_sticker_selection_before_base_image`

Tracks [GitHub #56](https://github.com/tomjoy75/camagru_php/issues/56) and `docs/feature_tree.md` (Editor → sticker selection / composition). **Parent thread:** “Capture disabled until sticker selected” (see **#57**, **#58** — out of scope here).

## Goal

Let an authenticated user **choose a sticker while the editor is still in the webcam-only / no-base-preview state** (`editorPreviewSrc` empty). Today the sticker strip is **disabled** (`opacity-50`, `pointer-events-none`) and only the full compose UI (hidden `sticker`, placement script) appears **after** a base image exists. Subject **V.4** expects a **superposable image** to be selected **before** capture is enabled; this feature delivers the **selection** prerequisite so **#57** can gate Capture meaningfully.

## Behavior

- **When there is no temp base image** (same condition as the current “Upload or capture…” sticker branch in `editor.php`): if stickers exist, show them as **interactive** choices (same identifiers as today: sticker **filename** as used by compose), not as inert previews only.
- **Selection** updates the same logical value the editor will use for composition (equivalent to today’s `#editor-compose-sticker` / `data-sticker` contract), so that **after** the user **captures or uploads** a base image, the chosen sticker is still known without forcing a second pick—unless the user changes it on the post-base UI.
- **When a base image already exists:** keep current behavior: full compose form, `editor_sticker_placement.js`, and existing compose/capture/upload flows **unchanged** except where a small shared hook is needed to avoid duplicate sticker-pick logic (prefer **minimal** reuse, not a large refactor).
- **Webcam script (`editor_webcam_preview.js`):** no requirement in **#56** to enable/disable Capture yet; that is **#57**. This spec only ensures **sticker choice is possible** in the no-base state.
- **Server:** if the current page reload after capture/upload drops client-only state, persist the pending sticker in a **server-supported** way (e.g. optional field on **POST `/editor/capture`** and **POST `/editor/upload`**, stored in `$_SESSION` and read when rendering the editor with a base), **or** another minimal approach that does not weaken validation on compose. Invalid sticker values must be rejected or ignored safely (no **500**, no trusting raw paths).

## Constraints

- **PHP standard library only**; **no** new frameworks or front-end libraries.
- **MVC:** views emit HTML only; controllers handle HTTP/session; sticker **validation** against the allowed list stays on the server (reuse the same rules as compose / `StickerService`-backed allow list).
- **Security:** never accept arbitrary filesystem paths from the client; treat sticker choice as an **allowed filename** (or slug) only, consistent with existing compose handling.
- **Explicitly out of scope:** disabling Capture until selection (**#57**); upload vs capture gating policy (**#58**); interactive placement on the **webcam** preview before capture (this issue is **selection**, not overlay preview); changing compose geometry rules or `ImageComposeService` beyond what is needed to carry the selected sticker into the existing compose path.

## Success Criteria

- On **`GET /editor`** with **no** workspace temp image and **with** stickers configured: user can **click** a sticker and the app records that choice (observable: DOM state, session, or subsequent compose default—per implementation).
- After **capture or upload** from that state, the editor shows the base image path as today and the user can run **Apply sticker** / compose without **having** to re-select **unless** they want a different sticker.
- **Regression:** flows that **already** work with base-first (upload then pick, pick on loaded base) remain correct; **no** new **500**s on editor, capture, upload, or compose for normal inputs.
- **Empty stickers:** unchanged messaging (“No stickers available.”).

## Implementation Plan

1 add server-side validation of a sticker filename against `StickerService` allow list for reuse in editor controller paths
2 in `editor.php` no-base branch, render interactive sticker picks and hidden `sticker` fields on capture and upload forms for JS to update
3 add minimal editor JS (no-base only) to handle pick clicks, sync hiddens, and selection / `aria-pressed` state
4 in `EditorController::capture` and `upload`, accept optional POST `sticker`, validate, set or clear `$_SESSION['editor_pending_sticker']`
5 when rendering editor with a base image, pre-fill `#editor-compose-sticker` from session when valid; clear pending on workspace reset and on invalid capture/upload sticker

## Tests

**Test cases**

- **Success**
  - **S1:** Authenticated **`GET /editor`** with **no** temp workspace and stickers present → **200**; HTML includes interactive sticker picks (`editor-sticker-pick`, `data-sticker`, `type="button"`), not only the old non-interactive strip.
  - **S2:** **`POST /editor/capture`** with valid `base_image_data` and valid **`sticker`** (e.g. `glasses.png`) → **302** to `/editor`; following **`GET /editor`** HTML has `#editor-compose-sticker` **value** equal to that filename (pending applied).
  - **S3:** **`POST /editor/upload`** with valid `base_image` file and valid **`sticker`** (e.g. `hat.png`) → **302**; following **`GET /editor`** shows `#editor-compose-sticker` pre-filled with `hat.png`.
- **Failure**
  - **F1:** **`POST /editor/capture`** with valid image data and **invalid** `sticker` (unknown filename or path-like value) → **non-500** (same capture success path as today if image OK); pending sticker must **not** stick—**`GET /editor`** must **not** show that bad value in `#editor-compose-sticker`.
  - **F2:** **`POST /editor/upload`** with valid image and **invalid** `sticker` → same expectations as **F1**.
- **Edge**
  - **E1:** After a valid pending sticker is stored, **`POST /editor/reset`** → **302**; next **`GET /editor`** has **no** temp preview and **empty** or default `#editor-compose-sticker` (pending cleared).
  - **E2:** **`POST /editor/capture`** (or upload) **without** `sticker` field → capture/upload behavior unchanged vs baseline; **no 500**; pending unchanged or absent per implementation.
  - **E3 (regression):** User with existing temp base (upload then **`GET /editor`**) still gets full compose UI and **`editor_sticker_placement.js`** behavior as before.

**Wire-up:** Run from **repository root** so `@public/stickers/...` paths work. Use a known sticker filename from `public/stickers/` (`glasses.png`, `hat.png`, `mustache.png`). Adjust **`BASE`** if needed. **`MINI_PNG`** is a 1×1 valid PNG data URL.

**Test Setup (authentication)**

```bash
rm -f cookies.txt
BASE=http://127.0.0.1:8080
STAMP=$(date +%s)
EMAIL="editor56_${STAMP}@example.com"
USER="editor56_${STAMP}"
PASS='Testpass1!'
MINI_PNG='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQmY9WkAAAAASUVORK5CYII='

curl -sS -o /dev/null -w "register %{http_code}\n" -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"
sqlite3 "${DATABASE_PATH:-database/camagru.db}" "UPDATE users SET email_verified = 1 WHERE email='$EMAIL';" 2>/dev/null || true
curl -sS -o /dev/null -w "login %{http_code}\n" -c cookies.txt -X POST "$BASE/login" \
  -d "username=$USER" -d "password=$PASS"

export BASE MINI_PNG USER PASS
```

**Execute tests**

```bash
BASE=${BASE:-http://127.0.0.1:8080}
MINI_PNG=${MINI_PNG:?run Test Setup first}
: "${USER:?run Test Setup first}"
: "${PASS:?run Test Setup first}"

# S1 — no-base editor shows interactive sticker picks (adjust greps if markup changes)
curl -sS -b cookies.txt "$BASE/editor" -o /tmp/editor56_s1.html
grep -q 'editor-sticker-pick' /tmp/editor56_s1.html && grep -q 'data-sticker=' /tmp/editor56_s1.html && echo "S1 OK: picks present" || echo "S1 CHECK: HTML"
grep -q 'pointer-events-none' /tmp/editor56_s1.html && echo "S1 NOTE: still has pointer-events-none somewhere — confirm stickers row is interactive"

# S2 — capture + pending sticker → compose hidden pre-filled
curl -sS -o /dev/null -w "capture S2 %{http_code}\n" -b cookies.txt -c cookies.txt -X POST "$BASE/editor/capture" \
  --data-urlencode "base_image_data=$MINI_PNG" \
  -d "sticker=glasses.png"
curl -sS -b cookies.txt "$BASE/editor" -o /tmp/editor56_s2.html
grep -q 'id="editor-compose-sticker"[^>]*value="glasses\.png"' /tmp/editor56_s2.html && echo "S2 OK" || echo "S2 CHECK: value attribute may be split across lines — inspect /tmp/editor56_s2.html"

# S3 — upload + sticker (run from repo root)
curl -sS -o /dev/null -w "upload S3 %{http_code}\n" -b cookies.txt -c cookies.txt -X POST "$BASE/editor/upload" \
  -F "base_image=@public/stickers/glasses.png" \
  -F "sticker=hat.png"
curl -sS -b cookies.txt "$BASE/editor" -o /tmp/editor56_s3.html
grep -q 'hat\.png' /tmp/editor56_s3.html && grep -q 'editor-compose-sticker' /tmp/editor56_s3.html && echo "S3 OK" || echo "S3 CHECK"

# F1 — invalid sticker on capture (image still valid)
curl -sS -o /dev/null -w "capture F1 %{http_code}\n" -b cookies.txt -c cookies.txt -X POST "$BASE/editor/capture" \
  --data-urlencode "base_image_data=$MINI_PNG" \
  -d "sticker=../../../etc/passwd"
curl -sS -b cookies.txt "$BASE/editor" | grep -q 'passwd' && echo "F1 FAIL: leaked path" || echo "F1 OK (no passwd in HTML)"

# F2 — invalid sticker on upload
curl -sS -o /dev/null -w "upload F2 %{http_code}\n" -b cookies.txt -c cookies.txt -X POST "$BASE/editor/upload" \
  -F "base_image=@public/stickers/glasses.png" \
  -F "sticker=nope_not_a_sticker.png"
curl -sS -b cookies.txt "$BASE/editor" | grep -q 'nope_not_a_sticker' && echo "F2 FAIL: bad sticker surfaced" || echo "F2 OK"

# E1 — reset clears workspace (and pending); re-check compose sticker empty/default
curl -sS -o /dev/null -w "reset %{http_code}\n" -b cookies.txt -c cookies.txt -X POST "$BASE/editor/reset"
curl -sS -b cookies.txt "$BASE/editor" -o /tmp/editor56_e1.html
grep -q 'editor-compose-sticker' /tmp/editor56_e1.html || echo "E1 OK: no compose form without base"
# if compose block absent when no base, pending cleared implicitly

# E2 — capture without sticker field (baseline path; fresh session after prior steps)
rm -f cookies.txt
curl -sS -c cookies.txt -X POST "$BASE/login" -d "username=$USER" -d "password=$PASS" >/dev/null
curl -sS -o /dev/null -w "capture E2 %{http_code}\n" -b cookies.txt -c cookies.txt -X POST "$BASE/editor/capture" \
  --data-urlencode "base_image_data=$MINI_PNG"
curl -sS -b cookies.txt "$BASE/editor" -o /tmp/editor56_e2.html
grep -q 'editor-compose-sticker' /tmp/editor56_e2.html && echo "E2: inspect value= on compose sticker (may be empty)" || echo "E2 OK"

# Manual — S1/S2/S3: in browser, open /editor with no base; click a sticker; capture or upload; confirm Apply sticker uses selection without re-picking.
```

**Manual / browser**

1. Log in, open **`/editor`** with no workspace image: confirm sticker thumbnails are **clickable** and show a **selected** state; confirm Capture/Upload forms submit the chosen **`sticker`** (DevTools → Network).
2. After capture or upload, confirm **Apply sticker** works with the pre-filled sticker; change sticker and compose again to confirm override still works.
3. Confirm **no** JavaScript errors on editor with and without base image.
