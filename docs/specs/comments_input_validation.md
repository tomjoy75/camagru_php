# Feature: Gallery comment input validation (server-side)

## Goal

Implement **[Comments] Input validation** ([GitHub #28](https://github.com/tomjoy75/camagru_php/issues/28)) on top of the existing comment form ([GitHub #27](https://github.com/tomjoy75/camagru_php/issues/27), `docs/specs/comments_comment_form.md`): validate and normalize **content** **before** `CommentRepository::insert`. This matches `docs/feature_tree.md` (Comments → Comment creation → **Input validation**: non-empty comments; length limit / sanitization). It keeps the same HTTP contract (`POST /gallery/comment`, `image_id`, `content`) and redirect/flash patterns as today.

## Behavior

- **Unchanged from #27:** Session gate, `image_id` validation, image existence check, wrong-method handling, success and generic failure flashes, redirect targets — same as `GalleryController::addComment` today unless this spec explicitly overrides.
- **Empty / whitespace-only:** After **trim**, if the body is empty → **no** insert; **302** to `GET /gallery/image?id=<id>` with the existing (or equivalent) empty-body error flash. Same as current behavior.
- **Maximum length:** After the **normalization** step below, if the body length exceeds **2000 bytes** (measured with PHP `strlen`) → **no** insert; **302** to the same detail URL with a **short** user-visible error flash (e.g. “Comment is too long.”). The limit applies to the string **passed to** `CommentRepository::insert` (stored form).
- **Sanitization (HTML):** Before length check and insert, remove HTML/XML-style markup from the trimmed body so stored content is **plain text**. Concretely: apply `strip_tags` with **no** allowed tags (empty allow list). Then **`trim` again** so cases like `<p>   </p>` collapse to empty. If the result is empty after that → treat as **empty** (same as whitespace-only: no insert, error flash).
- **Newlines / control characters:** Optionally normalize line endings for display consistency (e.g. collapse `\r\n` to `\n`); do not strip intentional single newlines inside the comment. **Must not** store null bytes: reject or strip `\0` before insert if present.
- **Persistence:** On success, call `CommentRepository::insert` with the **normalized** string (trimmed, tags stripped, length OK). Listing and detail pages continue to escape with `htmlspecialchars` when rendering.
- **Out of scope:** Comment moderation (#31), notifications (#35–#38), CSRF, client-side validation only, pagination, changing the DB schema, profanity filters.

## Constraints

- **MVC:** Validation and normalization live in the **controller** (or a tiny helper used only from the controller), not in the view. **No** HTML generation in services/repositories. `CommentRepository::insert` stays responsible for SQL only; it receives already-validated plain text.
- **PHP:** Standard library only (e.g. `strip_tags`, `strlen`, `trim`). No new dependencies.
- **Security:** All rules are enforced **server-side**. Do not trust `content` from `$_POST` for length or markup. Continue to bind author from session only. Do not expose stack traces on validation failure.
- **Consistency:** Reuse the same session flash keys or naming convention as the comment form spec for errors/success unless a dedicated key for “too long” / “invalid content” is clearly documented.

## Success Criteria

- **S1:** Authenticated user posts a comment within the limit and with plain text → **302** to detail; comment appears in the list unchanged in meaning (modulo explicit normalization rules above).
- **S2:** Body longer than **2000 bytes** after normalization → **no** new row; user sees an error flash on the detail page; count unchanged.
- **S3:** Body containing HTML tags (e.g. `<b>hi</b>`) → stored and displayed as plain text (tags not executed in browser; stored string without those tags).
- **S4:** Body that is only HTML (e.g. `<p></p>`) or only tags → **no** insert; same handling as empty comment (flash + no row).
- **S5:** Existing **#27** behaviors (guest POST, bad `image_id`, whitespace-only, `GET` on comment URL) remain correct and are covered by regression checks when this work ships.

## Implementation Plan

1. In `GalleryController::addComment`, keep the existing flow through session check, `image_id` parsing, and image existence validation; leave routing unchanged.
2. After reading `content` from `$_POST`, `trim` as today; if the result is empty, keep the current empty-body flash and redirect (no insert).
3. Normalize the trimmed string for storage: `strip_tags` with no allowed tags; **`trim` again**; remove ASCII NUL bytes (`\0`); optionally normalize line endings (`\r\n` and lone `\r` → `\n`). If the string is empty after this step, treat as empty (same flash + redirect as step 2, no insert).
4. If `strlen` of the normalized string exceeds **2000**, set `$_SESSION['gallery_comment_error']` to a short fixed message (e.g. “Comment is too long.”), `302` to `GET /gallery/image?id=<image_id>`, exit without calling `insert`.
5. Call `CommentRepository::insert($userId, $imageId, $normalizedString)` with the normalized value; keep existing `null` handling and success flash unchanged.
6. Confirm `GalleryController::showImage` still reads and clears `gallery_comment_error` / `gallery_comment_success` and that the detail view renders the error so new messages are visible (adjust only if the view does not already surface these keys).

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

### Test cases

**Success**

- **S1:** Authenticated `POST` with valid `image_id` and plain-text `content` (within limit) → **302** to detail; new text appears in the comment list; **Comments:** count increases by **1**.
- **S2 (boundary):** Authenticated `POST` with `content` exactly **2000** bytes after normalization (e.g. 2000 ASCII `x`) → **302** to detail; count increases; body visible on detail page.

**Failure**

- **F1:** Authenticated `POST` with normalized `content` length **> 2000** bytes → **no** new row; **302** to detail; response HTML after follow-redirect shows the **too-long** error message (same session flash mechanism as other comment errors); **Comments:** count unchanged vs before `POST`.
- **F2:** *(Regression / #27)* No session `POST` → **302** to `/login`; count unchanged on a known image.
- **F3:** *(Regression / #27, S5)* `GET` on the comment action URL (same path as `POST /gallery/comment`) → **404** (or router-consistent safe response), **no** `comments` mutation.

**Edge**

- **E1:** `content` is `<b>…unique token…</b>` → stored/displayed **without** tags; detail HTML contains the token as plain text, not as a literal `<b>` wrapper around it in the comment body (tags stripped before insert).
- **E2:** `content` is only markup with no text (e.g. `<p></p>` or equivalent) → **no** insert; count unchanged (empty after `strip_tags` + trim).
- **E3:** *(Regression / #27)* Whitespace-only body after trim → **no** insert; count unchanged.
- **E4:** Authenticated `POST` with a two-line body using **CRLF** (`\r\n`) between lines → **302** to detail; **Comments:** count increases; detail HTML contains **both** line tokens (each substring still present after optional normalization to `\n`).
- **E5:** Authenticated `POST` with `content` containing an ASCII **NUL** (e.g. URL-encoded `%00` in `application/x-www-form-urlencoded` body) → implementation **strips** `\0` before insert; stored text has **no** NUL; **302** and visible comment matches the **concatenated** non-NUL text (e.g. `a\x00b` → stored `ab`). **Note:** if the PHP/runtime stack drops bytes after `\0` before your code runs, this check may **SKIP** — then verify NUL handling manually in the browser or a controlled POST.

### Test Setup (authentication)

```bash
rm -f cookies.txt
BASE=http://localhost:8080
COMMENT_POST="${BASE}/gallery/comment"

STAMP=$(date +%s)
EMAIL="comment_val_${STAMP}@example.com"
USER="commentval${STAMP}"
PASS='Testpass1!'

KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -s "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}

curl -s -o /dev/null -c cookies.txt -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"
curl -s -o /dev/null -c cookies.txt -b cookies.txt -L -X POST "$BASE/login" \
  -d "username=$USER" -d "password=$PASS"
```

Adjust **`COMMENT_POST`**, field names **`image_id`** / **`content`**, and **`BASE`** if your server differs.

### Execute tests

```bash
BASE=http://localhost:8080
COMMENT_POST="${BASE}/gallery/comment"
KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -s "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}

comment_count() {
  curl -s -b cookies.txt "$BASE/gallery/image?id=$1" | tr '\n' ' ' | sed -n 's/.*Comments:<\/dt>[[:space:]]*<dd[^>]*>[[:space:]]*\([0-9][0-9]*\).*/\1/p' | head -1
}

# F3 — GET on comment URL must not mutate (S5 / #27 regression)
curl -s -o /dev/null -w "F3 GET comment URL HTTP %{http_code} (expect 404)\n" "$COMMENT_POST"

# F2 — guest POST must not insert
curl -s -o /dev/null -D /tmp/cmt_val_f2.txt -w "F2 unauth POST %{http_code}\n" -X POST "$COMMENT_POST" \
  -d "image_id=$KNOWN_GOOD_ID" -d "content=nope"
grep -i '^location:' /tmp/cmt_val_f2.txt || true

if [ -z "$KNOWN_GOOD_ID" ] || [ ! -s cookies.txt ]; then
  echo "SKIP: set KNOWN_GOOD_ID and run Test Setup (authentication)"
  exit 0
fi

# E3 — whitespace-only (regression)
before_e3=$(comment_count "$KNOWN_GOOD_ID")
curl -s -o /dev/null -b cookies.txt -L -X POST "$COMMENT_POST" \
  -d "image_id=$KNOWN_GOOD_ID" -d "content=%20%20"
after_e3=$(comment_count "$KNOWN_GOOD_ID")
echo "E3 count before=$before_e3 after=$after_e3 (expect equal)"

# E2 — tag-only body -> no insert
before_e2=$(comment_count "$KNOWN_GOOD_ID")
curl -s -o /dev/null -b cookies.txt -L -X POST "$COMMENT_POST" \
  -d "image_id=$KNOWN_GOOD_ID" -d "content=%3Cp%3E%3C%2Fp%3E"
after_e2=$(comment_count "$KNOWN_GOOD_ID")
echo "E2 count before=$before_e2 after=$after_e2 (expect equal)"

# F1 — body longer than 2000 bytes after normalization
LONG_2001=$(python3 -c "print('x' * 2001)")
before_f1=$(comment_count "$KNOWN_GOOD_ID")
html_f1=$(curl -s -b cookies.txt -L -X POST "$COMMENT_POST" \
  --data-urlencode "image_id=$KNOWN_GOOD_ID" --data-urlencode "content=$LONG_2001" \
  -w "\n%{http_code}")
code_f1=$(echo "$html_f1" | tail -n1)
body_f1=$(echo "$html_f1" | sed '$d')
after_f1=$(comment_count "$KNOWN_GOOD_ID")
echo "F1 HTTP last=$code_f1 count before=$before_f1 after=$after_f1 (expect equal)"
echo "$body_f1" | grep -qi 'too long' && echo "F1 OK: too-long message in page" || echo "F1 NOTE: grep for flash copy (adjust if message text differs)"

# S2 — exactly 2000 bytes
EXACT_2000=$(python3 -c "print('y' * 2000)")
before_s2=$(comment_count "$KNOWN_GOOD_ID")
curl -s -o /dev/null -b cookies.txt -L -X POST "$COMMENT_POST" \
  --data-urlencode "image_id=$KNOWN_GOOD_ID" --data-urlencode "content=$EXACT_2000"
after_s2=$(comment_count "$KNOWN_GOOD_ID")
echo "S2 count before=$before_s2 after=$after_s2 (expect after = before + 1)"

# S1 — plain unique comment
U="plain_val_${RANDOM}"
before_s1=$(comment_count "$KNOWN_GOOD_ID")
curl -s -o /dev/null -b cookies.txt -L -X POST "$COMMENT_POST" \
  -d "image_id=$KNOWN_GOOD_ID" -d "content=$U"
after_s1=$(comment_count "$KNOWN_GOOD_ID")
html_s1=$(curl -s -b cookies.txt "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
echo "$html_s1" | grep -qF "$U" && echo "S1 OK: plain text visible" || echo "S1 FAIL: token missing"
echo "S1 count before=$before_s1 after=$after_s1"

# E1 — HTML stripped; token must appear, angle brackets not wrapping it as raw HTML in comment cell
TOK="strip_${RANDOM}"
curl -s -o /dev/null -b cookies.txt -L -X POST "$COMMENT_POST" \
  -d "image_id=$KNOWN_GOOD_ID" -d "content=%3Cb%3E${TOK}%3C%2Fb%3E"
html_e1=$(curl -s -b cookies.txt "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
echo "$html_e1" | grep -qF "$TOK" && echo "E1 OK: token present" || echo "E1 FAIL: token missing"
echo "$html_e1" | grep -qF "<b>${TOK}</b>" && echo "E1 WARN: raw <b> wrapper still in HTML (unexpected if stripped)" || echo "E1 OK: no raw <b>…</b> around token in page source"

# E4 — CRLF multiline (both line tokens visible on detail)
R4=${RANDOM}
E4_LINE1="e4a_${R4}"
E4_LINE2="e4b_${R4}"
E4_CONTENT="${E4_LINE1}"$'\r\n'"${E4_LINE2}"
before_e4=$(comment_count "$KNOWN_GOOD_ID")
curl -s -o /dev/null -b cookies.txt -L -X POST "$COMMENT_POST" \
  --data-urlencode "image_id=$KNOWN_GOOD_ID" --data-urlencode "content=${E4_CONTENT}"
after_e4=$(comment_count "$KNOWN_GOOD_ID")
html_e4=$(curl -s -b cookies.txt "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
echo "E4 count before=$before_e4 after=$after_e4 (expect +1)"
echo "$html_e4" | grep -qF "$E4_LINE1" && echo "E4 OK: first line token present" || echo "E4 FAIL: first line missing"
echo "$html_e4" | grep -qF "$E4_LINE2" && echo "E4 OK: second line token present" || echo "E4 FAIL: second line missing"

# E5 — NUL in urlencoded body; expect stripped to nulzap (see spec if PHP truncates)
before_e5=$(comment_count "$KNOWN_GOOD_ID")
NUL_BODY=$(python3 -c "import urllib.parse,sys; print(urllib.parse.urlencode({'image_id': sys.argv[1], 'content': 'nul\x00zap'}))" "$KNOWN_GOOD_ID")
curl -s -o /dev/null -b cookies.txt -L -X POST "$COMMENT_POST" -d "$NUL_BODY"
after_e5=$(comment_count "$KNOWN_GOOD_ID")
html_e5=$(curl -s -b cookies.txt "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
echo "E5 count before=$before_e5 after=$after_e5 (expect +1 if POST reaches PHP with both sides of NUL)"
if echo "$html_e5" | grep -qF 'nulzap'; then
  echo "E5 OK: concatenated text without NUL visible"
else
  echo "E5 NOTE: nulzap not found — PHP/runtime may truncate at %00; verify manually (spec E5)"
fi
```

Run from a shell with **`python3`** available (fixed-length bodies, NUL urlencoding). **`grep` for “too long”** must match the final **too-long** flash string. **F3** expects **404**; if the router returns another safe non-mutating code, adjust the expectation in your tracker notes. **Order matters:** later steps add comments; use a fresh DB or accept monotonic growth when re-running.
