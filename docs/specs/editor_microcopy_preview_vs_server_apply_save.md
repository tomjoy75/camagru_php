# Feature: `editor_microcopy_preview_vs_server_apply_save`

Goal  
Reduce confusion about what users are looking at in the Editor: the draggable overlay in **BASE_READY** is a **client-side preview** aligned with the next compose request; **Apply sticker** performs **server-side** merging; **COMPOSED_READY** shows the **last server-composed** workspace image; **Save image** persists only what the server has already composed after a successful Apply—not ad‑hoc overlay pixels if preview and server ever diverge.

Behavior  
- In **BASE_READY**, short helper copy appears near the workspace preview and/or **Apply sticker** so a reader understands: placement is previewed in the browser; **Apply** submits to the server and merges the sticker onto the base.  
- When **Save image** is shown (**COMPOSED_READY**), short helper copy clarifies that Save stores the **last successful server composition**, not “whatever the overlay looked like” without a matching Apply.  
- Existing **editor-guidance** messages (#68) remain the primary “what to do next”; this feature **disambiguates preview vs applied vs saved** without repeating long paragraphs or replacing those messages.  
- Copy is implemented primarily in `src/views/editor.php`; optional `title`, `aria-describedby`, or minimal JS hooks only if needed for accessibility. No new `editorState` values and no change to compose/save HTTP contracts unless a wording-only flash key is unavoidable (prefer avoiding).

Constraints  
- MVC: views emit HTML and escaped strings only; no business logic in the view beyond conditional visibility by existing `$editorState` / flags already passed from the controller.  
- Do not change `EditorController` compose/save routes or session rules except as strictly needed for copy (avoid).  
- Align wording with `docs/specs/editor_ui_flow.md` where applicable.  
- No redesign of editor layout; no change to sticker overlay behavior beyond explanatory text.

Success Criteria  
- A new user can infer without reading internal docs that **Apply** triggers **server** merge and updates the workspace image from the server response path.  
- A new user can infer that **Save** persists the **already merged** result from the server, not an unofficial overlay-only state.  
- **EMPTY**, sticker-first entry, Apply disabled without selection, and save gating behaviors remain unchanged (manual regression).  
- Guidance strip (#68) still reads as the main step-by-step hint; new lines are supplementary one-liners, not duplicate essays.

## Implementation Plan

1 add conditional helper line under the workspace preview when `BASE_READY` and a workspace image renders (`editor-preview-host` / `editor-base-preview-img` region)
2 add conditional helper line adjacent to **Apply sticker** (`editor-compose-submit`), optionally linking ids for `aria-describedby` / `title` on the same branch as the compose form
3 add conditional helper line next to the **Save image** form when `COMPOSED_READY`
4 if using `aria-describedby`, assign stable `id`s on the new helper elements and reference them from the button(s); skip JS unless a live region is strictly required
5 manually verify copy in `EMPTY`, `BASE_READY`, and `COMPOSED_READY`; confirm sticker-first gates, Apply disabled-without-sticker, and save visibility rules unchanged

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

**Test cases**

**Success**

- **S1:** Authenticated **GET `/editor`** when the session is **BASE_READY** (workspace image + sticker chosen) includes the new preview/Apply helper copy (implementation exposes a stable marker—e.g. `id="editor-help-preview-apply"`—for grep).
- **S2:** Authenticated **GET `/editor`** when the session is **COMPOSED_READY** includes the Save helper copy (stable marker—e.g. `id="editor-help-save-composed"`).
- **S3:** `#editor-guidance-message` remains present and remains the main guidance strip (same element id; primary “what to do next” copy not removed).

**Failure**

- **F1:** Unauthenticated **GET `/editor`** redirects to **`/login`** (302/303 with `Location`), unchanged from baseline.
- **F2:** **EMPTY** editor HTML does **not** contain the COMPOSED-only Save helper marker (`editor-help-save-composed`).

**Edge**

- **E1:** Helper copy uses existing escaping (`htmlspecialchars` / literal-only strings); no new user-controlled HTML in these nodes (spot-check in DevTools or source).
- **E2:** After **upload** only (BASE_READY), Save helper marker absent; after **compose**, Save helper marker present—state gating matches editor rules.

**Test Setup (authentication)**

```bash
rm -f cookies.txt
BASE=http://localhost:8080
DATABASE_PATH=database/camagru.db
STAMP=$(date +%s)
EMAIL="microcopy.${STAMP}@example.com"
USERNAME="microcopy${STAMP}"
PASSWORD='Aa1!testpass'

# php -S 127.0.0.1:8080 public/index.php   # run from repo root in another terminal if needed

curl -s -i -c cookies.txt -X POST "$BASE/register" \
  -d "email=$EMAIL&username=$USERNAME&password=$PASSWORD&confirm_password=$PASSWORD" -o /tmp/microcopy_reg.txt

TOKEN="$(sqlite3 "$DATABASE_PATH" "SELECT confirmation_token FROM users WHERE email='$EMAIL' ORDER BY id DESC LIMIT 1;")"
curl -s -o /tmp/microcopy_confirm.txt "$BASE/register/confirm?token=$TOKEN"

curl -s -i -c cookies.txt -b cookies.txt -X POST "$BASE/login" \
  -d "username=$USERNAME&password=$PASSWORD" -o /tmp/microcopy_login.txt
```

**Execute tests**

Markers (`editor-help-preview-apply`, `editor-help-save-composed`) must match the **`id` attributes** added in `src/views/editor.php`. Adjust grep targets if implementation uses different stable ids—keep **one grep target per helper block**.

Run **Test Setup (authentication)** first (cookies only—no upload yet—so **EMPTY** can be asserted).

```bash
BASE=http://localhost:8080

php -r '$im=imagecreatetruecolor(320,240);$bg=imagecolorallocate($im,240,240,240);imagefill($im,0,0,$bg);imagepng($im,"/tmp/microcopy_base.png");imagedestroy($im);'

# F2 — EMPTY (logged in, no workspace yet): Save helper marker absent
curl -s -b cookies.txt "$BASE/editor" -o /tmp/editor_empty.html
! grep -q 'id="editor-help-save-composed"' /tmp/editor_empty.html

# S1 / E2 (partial) — BASE_READY after upload: preview/Apply helper present; Save helper still absent
curl -s -i -b cookies.txt -F "base_image=@/tmp/microcopy_base.png;type=image/png" -F "sticker=hat.png" \
  "$BASE/editor/upload" -o /tmp/microcopy_upload.txt
curl -s -b cookies.txt "$BASE/editor" -o /tmp/editor_base_ready.html
grep -q 'id="editor-help-preview-apply"' /tmp/editor_base_ready.html
! grep -q 'id="editor-help-save-composed"' /tmp/editor_base_ready.html

# S2 / E2 — COMPOSED_READY: compose then GET /editor; Save helper present
curl -s -i -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=hat.png&x=10&y=10&scale=1&angle=0" -o /tmp/microcopy_compose.txt
curl -s -b cookies.txt "$BASE/editor" -o /tmp/editor_composed.html
grep -q 'id="editor-help-save-composed"' /tmp/editor_composed.html

# F1 — guest cannot load editor
curl -s -D /tmp/guest_editor.hdr -o /tmp/guest_editor.body "$BASE/editor"
grep -i '^Location:.*/login' /tmp/guest_editor.hdr

# S3 — guidance strip still present
grep -q 'id="editor-guidance-message"' /tmp/editor_composed.html
```

**Manual (recommended):** Skim **EMPTY** vs **BASE_READY** vs **COMPOSED_READY** in a browser; confirm sticker-first gating, disabled **Apply** without sticker, and **Save** visibility unchanged vs pre-feature behavior.
