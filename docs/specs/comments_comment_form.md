# Feature: Gallery image comment form (submit)

## Goal

On the **gallery image detail** page (`GET /gallery/image?id=…`), let **authenticated** users post a **new comment** via a visible **textarea + submit** control. This implements [GitHub #27](https://github.com/tomjoy75/camagru_php/issues/27) and `docs/feature_tree.md` (“Comment form — available only to logged-in users; textarea + submit for each image”). It builds on **[Comments] Persistence** (`docs/specs/comments_persistence.md`, `CommentRepository::insert`) and the existing detail page (`docs/specs/gallery_image_details.md`, `docs/specs/comments_listing_per_image.md`).

## Behavior

- **Who sees the form:** Only when a valid session user id is present (same notion as the like control on the same page). Guests see **no** comment form; the public comment list stays unchanged.
- **Where:** The form appears on the **same** detail view as today, logically near the **Comments** section (above or below the list — pick one in implementation and keep it consistent).
- **HTTP:** `POST` to a **dedicated** route (e.g. `POST /gallery/comment`) with **`image_id`** (positive integer, same image as the detail page) and **`content`** (raw comment body from the textarea). After a **successful** insert, respond with **302** to `GET /gallery/image?id=<image_id>` so the new comment appears in the list and counts stay consistent.
- **Auth:** No session → **302** to `/login` (or project-standard auth redirect), **no** `comments` row inserted (mirror `GalleryController::toggleLike` guest behavior).
- **Image id:** Missing, non-numeric, or non-positive `image_id`, or **no** row in `images` for that id → **no** insert; **302** to a safe place (e.g. `/gallery` or back to detail if you can recover a valid id — document the chosen rule; align with like-toggle’s unknown-id handling).
- **Minimal content rule (until #28):** If `content` is **empty or whitespace-only** after trim, **do not** call `insert`; **302** back to `GET /gallery/image?id=…` when the image id is valid, with a **short user-visible error** (e.g. session flash) so the user knows the comment was not saved. **Out of scope for this spec:** max length, HTML stripping, and richer validation — [GitHub #28](https://github.com/tomjoy75/camagru_php/issues/28).
- **Persistence:** On success, use existing `CommentRepository::insert(sessionUserId, imageId, trimmedContent)`; `null` return → treat as failure (safe redirect + optional flash, no stack trace).
- **Wrong HTTP method:** `GET` on the comment POST URL → **404** (router-consistent), no mutation.
- **Out of scope:** Comment moderation (#31), notifications (#35–#38), AJAX, pagination, CSRF tokens (none in project today), and **full** validation spec (#28).

## Constraints

- **MVC:** Router → `GalleryController` (or equivalent) handles HTTP, session, redirects, and optional flash; **repository** owns SQL (`CommentRepository::insert` already exists); **view** outputs HTML only and escapes dynamic strings with `htmlspecialchars`.
- **PHP:** Standard library only; prepared statements for all SQL (insert already in repository).
- **Security:** Never trust client for **user id** — bind author from session only. Validate **`image_id`** before insert. Escape all user-derived output in the view. Do not expose stack traces or SQL to the client.
- **Consistency:** Match patterns from `GalleryController::toggleLike`, `gallery_image.php` like form, and `docs/specs/gallery_like_toggle.md` for redirects and guest handling.

## Success Criteria

- **S1:** Logged-in user on a valid detail page submits a **non-empty** comment → **302** to same detail URL; new row appears in the comment list and **Comments:** count increases by **1**.
- **S2:** Guest (no session) `POST` → **302** to login; **no** new comment row for that attempt.
- **S3:** Logged-in user submits **whitespace-only** or empty body → **no** insert; user sees an error indication after redirect (e.g. flash).
- **S4:** Invalid or unknown `image_id` → **no** insert; safe redirect without **500**.
- **S5:** `GET` on the comment action URL → **404** (or equivalent), no mutation.
- **S6:** Guest `GET` detail → **200**; no comment form in the body (or no working submit without session).

## Implementation Plan

1. Register `POST /gallery/comment` (or chosen path) in `src/routes/index.php` → `GalleryController::addComment` (or equivalent).
2. In the handler: no session → `302` `/login`, exit; no DB writes.
3. Read POST `image_id`, validate positive int; verify image exists (reuse repository pattern from like toggle); on failure safe `302` to `/gallery` (or documented alternative).
4. Read POST `content` as string, trim; if empty after trim, set error flash and `302` to `GET /gallery/image?id=<id>`; exit without insert.
5. Call `CommentRepository::insert` with session user id, image id, trimmed content; on `null`, set generic error flash and redirect to detail (or `/gallery`).
6. On success, optional success flash, then `302` to `GET /gallery/image?id=<id>`.
7. In `gallery_image.php`, when logged in and detail loaded successfully (`$detailLoadError` false), render `<form method="post">` to the comment URL with hidden `image_id`, `<textarea name="content">`, submit button; display flash errors if the layout/controller exposes them.

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

### Test cases

**Success**

- **S1:** After **Test Setup**, authenticated `POST` with valid `image_id` and non-empty `content` → **302** with `Location` containing `/gallery/image?id=<id>`; subsequent `GET` detail shows the new text in the comments list and **Comments:** count increased vs before.
- **S2:** Second `POST` with another non-empty body on the same image → list shows both; count reflects total (ordering oldest-first per existing listing).

**Failure**

- **F1:** No session → **302** to `/login`; comment count for that image unchanged.
- **F2:** Authenticated `POST` with missing / invalid / non-positive `image_id` → no **500**; no new comment for a known-good image id from a malformed request (verify count unchanged on `KNOWN_GOOD_ID` if applicable).
- **F3:** Authenticated `POST` with valid `image_id` but **unknown** image id (no `images` row) → no insert; safe redirect.
- **F4:** `GET` on the comment POST URL → **404** (or router-consistent), no mutation.

**Edge**

- **E1:** Authenticated `POST` with valid `image_id` and body that is **only spaces/newlines** → **no** insert (same as S3); count unchanged.
- **E2:** Guest `GET /gallery/image?id=<valid>` → **200**; HTML does **not** contain a working comment form that posts without auth (e.g. no form or form only when session exists — grep for `textarea` + comment action as appropriate).

### Test Setup (authentication)

```bash
rm -f cookies.txt
BASE=http://localhost:8080
COMMENT_POST="${BASE}/gallery/comment"

STAMP=$(date +%s)
EMAIL="comment_form_${STAMP}@example.com"
USER="commenter${STAMP}"
PASS='Testpass1!'

KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -s "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}

curl -s -o /dev/null -c cookies.txt -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"
curl -s -o /dev/null -c cookies.txt -b cookies.txt -L -X POST "$BASE/login" \
  -d "email=$EMAIL" -d "password=$PASS"
```

Adjust **`COMMENT_POST`**, field names **`image_id`** and **`content`**, and path if implementation differs.

### Execute tests

```bash
BASE=http://localhost:8080
COMMENT_POST="${BASE}/gallery/comment"
KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -s "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}

comment_count() {
  curl -s -b cookies.txt "$BASE/gallery/image?id=$1" | tr '\n' ' ' | sed -n 's/.*Comments:<\/dt>[[:space:]]*<dd[^>]*>[[:space:]]*\([0-9][0-9]*\).*/\1/p' | head -1
}

# F4 — GET on comment URL must not mutate
curl -s -o /dev/null -w "F4 GET comment URL -> %{http_code}\n" "$COMMENT_POST"

if [ -z "$KNOWN_GOOD_ID" ]; then
  echo "SKIP: set KNOWN_GOOD_ID or ensure /gallery lists gallery/image?id="
  exit 0
fi

# F1 — unauthenticated POST -> redirect to login
curl -s -o /dev/null -D /tmp/cmt_hdr.txt -w "F1 unauth POST HTTP %{http_code}\n" -X POST "$COMMENT_POST" \
  -d "image_id=$KNOWN_GOOD_ID" -d "content=should not persist"
grep -i '^location:' /tmp/cmt_hdr.txt || true

# E1 — whitespace-only body should not increase count (needs cookies.txt from Test Setup)
if [ -s cookies.txt ]; then
  before=$(comment_count "$KNOWN_GOOD_ID")
  curl -s -o /dev/null -b cookies.txt -L -X POST "$COMMENT_POST" \
    -d "image_id=$KNOWN_GOOD_ID" -d "content=%20%20%20"
  after=$(comment_count "$KNOWN_GOOD_ID")
  echo "E1 comment count before=$before after=$after (expect equal)"
fi

# S1 — post unique comment and grep body on detail (needs cookies.txt)
if [ -s cookies.txt ]; then
  U="curl_s1_${RANDOM}_body"
  before=$(comment_count "$KNOWN_GOOD_ID")
  curl -s -o /dev/null -D /tmp/cmt_s1.txt -b cookies.txt -L -X POST "$COMMENT_POST" \
    -d "image_id=$KNOWN_GOOD_ID" -d "content=$U"
  grep -i '^location:' /tmp/cmt_s1.txt || true
  after=$(comment_count "$KNOWN_GOOD_ID")
  html=$(curl -s -b cookies.txt "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
  echo "$html" | grep -qF "$U" && echo "S1 OK: new comment visible" || echo "S1 FAIL: body not found"
  echo "S1 count before=$before after=$after"
fi

# E2 — guest GET detail: optional check for comment form (implementation-dependent)
html_guest=$(curl -s "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
echo "$html_guest" | grep -q 'name="content"' && echo "E2 NOTE: guest page contains content field — confirm form is absent or non-postable without session"
```

Manual follow-up: confirm flash messages for **S3** / **F2** / **F3** in browser; align **`COMMENT_POST`** and form field names with final routes.
