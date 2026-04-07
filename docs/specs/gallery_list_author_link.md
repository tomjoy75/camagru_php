# Feature: Gallery — author on list tiles (link to user filter)

## Goal

Make the **existing** “gallery filtered by author” behavior (**`GET /gallery?user_id=…`**) **discoverable** from the **public gallery grid**: each tile shows **who published the image** and offers a **safe GET link** to that author’s filtered gallery. No new routes; sorting and pagination behavior for the **current** view stay consistent with today’s implementation.

## Behavior

- On **`GET /gallery`** (with or without `user_id`, `sort`, `page`), when the gallery **loads successfully** and the grid is shown, **each image tile** displays the **author’s public display name** (same field as elsewhere, e.g. **`users.username`**).
- A **dedicated control** (link or clearly clickable author label) on or next to the tile issues **`GET /gallery?user_id=<author_user_id>`**, where **`author_user_id`** is the **`images.user_id`** for that row (positive integer).
- **Preserve active sort** when building the author link: if the visitor is using a non-default **`sort`** (anything other than the default **newest** mode), the author link includes the same **`sort=…`** value so the filtered view keeps the chosen ordering. If **`sort`** is default, omit it from the query string (same convention as existing sort controls).
- **Do not** carry the current **`page=`** into the author filter link: choosing an author starts that author’s gallery at **page 1** (new filter context).
- The **tile’s primary navigation to the image detail** (**`GET /gallery/image?id=…`**) remains available; nesting links invalid HTML must be avoided (e.g. **no** `<a>` inside `<a>`). Prefer a layout where the **thumbnail / “Likes:”** area links to detail and the **author line** is a separate link, or another accessible pattern that preserves both actions.
- **Repository / list row shape:** list queries supply **`user_id`** (author) and **`username`** (or equivalent) for every row returned to the gallery view, including the **unfiltered** list (today’s unfiltered query may not join **`users`**; this feature requires author fields for **all** listed images).
- **Empty and error states** unchanged: no author line required where there is no grid; load-error copy stays as today.

## Constraints

- **MVC:** Author fields come from the **repository** (parameterized SQL, **`JOIN users`** as needed); the **controller** passes rows to the view unchanged except for existing filtering of missing files; the **view** outputs HTML only and **escapes** usernames and all URL fragments with **`htmlspecialchars`** (UTF-8, quotes mode for attributes).
- **Stack:** PHP standard library only; no new frameworks; **vanilla JS not required** for the core behavior.
- **Security:** **`user_id`** in generated URLs must be a **validated integer** from server-side data, not from client input; never reflect raw query strings into attributes without encoding; do not expose **`users.email`** or other non-public profile fields unless the product already shows them elsewhere (this feature uses **username** only).
- **Compatibility:** Do **not** change **`user_id` / `sort` / `page`** parsing rules, count/list semantics, or pagination **`href`** composition except to **add** author display and links; existing **`data-image-id`** / **`data-like-count`** contracts used by tests should remain valid.

## Success Criteria

- Unfiltered **`GET /gallery`**: each visible tile shows an **author name** and an author link whose **`href`** is **`/gallery?user_id=<id>`** (plus **`&sort=…`** only when a non-default sort is active).
- Following an author link shows the **filtered** gallery for that user (**same rules** as the existing filter feature: only that user’s images, correct empty copy if none).
- With **`sort`** set to a non-default mode, the author link includes **`sort`**; after navigation, **sort controls** and ordering remain consistent with that mode.
- **HTML validity:** no nested interactive elements; detail navigation and author filter link both work in a normal browser.
- **Regression:** Pagination and sort links still preserve **`user_id`** when a filter is active; **`curl`** or manual checks confirm **200** responses and **no** uncaught errors on typical gallery pages.

## Implementation Plan

1. Extend `ImageRepository::findPageForPublicGallery` with a `users` join and selected `user_id` / `username` in both filtered and unfiltered SQL branches; update the row-shape phpdoc.
2. Restructure each tile in `gallery.php` so the detail `<a>` wraps only the thumbnail and “Likes:” row and keeps `data-image-id` / `data-like-count` on that `<a>`.
3. For each tile, build `authorFilterHref` as `/gallery?…` with `user_id` from the row and `sort` only when `$gallerySort` is not `newest` (mirror existing sort-query rules).
4. Render a separate author `<a href="…">` with `htmlspecialchars` on username (and on the full `href`) below or beside the detail link block, with no nested anchors.
5. Verify `GET /gallery` (and one non-default `sort`): **200**, author links query shape, no nested `<a>`, `data-image-id` / `data-like-count` still on the detail `<a>`.

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

### Test cases

**Success**

- **S1:** `GET /gallery` → **200**; HTML includes at least one `href` to **`/gallery?user_id=<positive-int>`** (percent-encoding allowed); each listed image still has **`data-image-id`** and **`data-like-count`** on the **`/gallery/image?id=…`** detail `<a>` (same markup contract as today).
- **S2:** `GET /gallery?sort=likes` (or another non-default whitelisted `sort`) → **200**; at least one author filter `href` includes **`sort=`** that matches the current sort mode **and** **`user_id=`** (order of query parameters free).
- **S3:** `GET /gallery?user_id=<owner>` when that owner has images → **200**; every visible **`data-image-id`** on the page is an `images.id` whose **`user_id`** equals `<owner>` (SQLite spot-check).

**Failure**

- **F1:** Typo path (e.g. **`GET /gallerys`**) → **404**; router unchanged.

**Edge**

- **E1:** `GET /gallery?page=2` (when a second page exists) → **200**; author filter links must **not** include **`page=`** (new author context starts at page 1).
- **E2:** No **nested `<a>`**: the fragment starting at the detail `<a>` that carries **`data-image-id`** and **`href="/gallery/image?id=…"`** up to its matching closing **`</a>`** must not contain another **`<a`**.

### Test Setup

```bash
set -uo pipefail
BASE=http://localhost:8080
DATABASE_PATH="${DATABASE_PATH:-$(pwd)/database/camagru.db}"
```

### Execute tests

```bash
set -uo pipefail
BASE=http://localhost:8080
DATABASE_PATH="${DATABASE_PATH:-$(pwd)/database/camagru.db}"
FAILED=0
bump_fail() { echo "FAIL: $*"; FAILED=$((FAILED + 1)); }

# S1
code=$(curl -s -o /tmp/gal.html -w "%{http_code}" "$BASE/gallery")
[ "$code" = "200" ] || bump_fail "S1 status $code"
grep -qE 'href="[^"]*\/gallery\?user_id=[0-9]' /tmp/gal.html \
  || grep -qE "href='[^']*\/gallery\?user_id=[0-9]" /tmp/gal.html \
  || bump_fail "S1 missing author filter href"
grep -q 'data-image-id="' /tmp/gal.html && grep -q 'data-like-count="' /tmp/gal.html \
  || bump_fail "S1 missing data-image-id / data-like-count"

# S2
code=$(curl -s -o /tmp/gal_sort.html -w "%{http_code}" "$BASE/gallery?sort=likes")
[ "$code" = "200" ] || bump_fail "S2 status $code"
if ! grep -oE 'href="[^"]*"' /tmp/gal_sort.html | grep -q 'user_id=[0-9]'; then
  bump_fail "S2 missing user_id in some href"
fi
if ! grep -oE 'href="[^"]*"' /tmp/gal_sort.html | grep -q 'sort=likes'; then
  bump_fail "S2 missing sort=likes in author-related href"
fi

# S3 (skip if DB empty)
OWNER=$(sqlite3 "$DATABASE_PATH" "SELECT user_id FROM images ORDER BY created_at DESC LIMIT 1;" 2>/dev/null || true)
if [ -n "$OWNER" ] && [ "$OWNER" != "" ]; then
  code=$(curl -s -o /tmp/gal_f.html -w "%{http_code}" "$BASE/gallery?user_id=$OWNER")
  [ "$code" = "200" ] || bump_fail "S3 status $code"
  while IFS= read -r iid; do
    [ -z "$iid" ] && continue
    uid=$(sqlite3 "$DATABASE_PATH" "SELECT user_id FROM images WHERE id=$iid;" 2>/dev/null || echo "")
    [ "$uid" = "$OWNER" ] || bump_fail "S3 image $iid user_id $uid != $OWNER"
  done < <(grep -oE 'data-image-id="[0-9]+"' /tmp/gal_f.html | grep -oE '[0-9]+' | sort -u)
else
  echo "S3 skip (no images in DB)"
fi

# F1
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/gallerys")
[ "$code" = "404" ] || bump_fail "F1 expected 404 got $code"

# E1 (skip if only one page)
code=$(curl -s -o /tmp/gal_p2.html -w "%{http_code}" "$BASE/gallery?page=2")
if [ "$code" = "200" ] && grep -q 'data-image-id="' /tmp/gal_p2.html; then
  if grep -oE 'href="[^"]*user_id=[0-9][^"]*"' /tmp/gal_p2.html | grep -qE 'page='; then
    bump_fail "E1 author href must not include page="
  fi
else
  echo "E1 skip (page 2 empty or unavailable)"
fi

# E2 — no inner <a> before the closing </a> of the detail tile anchor
python3 - "$BASE/gallery" <<'PY'
import re, sys, urllib.request
base = sys.argv[1]
html = urllib.request.urlopen(base).read().decode("utf-8", "replace")
open_re = re.compile(
    r'<a\b(?=[^>]*\bhref="/gallery/image\?id=\d+")(?=[^>]*\bdata-image-id="\d+")[^>]*>',
    re.I,
)
for m in open_re.finditer(html):
    pos = m.end()
    while True:
        m_open = re.search(r"<a(\s|>)", html[pos:], re.I)
        m_close = re.search(r"</a\s*>", html[pos:], re.I)
        if not m_close:
            break
        close_idx = pos + m_close.start()
        if m_open and pos + m_open.start() < close_idx:
            sys.exit("E2 nested <a> inside detail gallery tile link")
        break
print("E2 ok")
PY
[ $? -eq 0 ] || bump_fail "E2 nested anchor check"

[ "$FAILED" -eq 0 ] && echo "All executed tests passed." || exit 1
```

Requires **`bash`**, **`curl`**, **`sqlite3`**, **`python3`**; run from **repository root** with **`php -S`** (or your vhost) serving **`$BASE`**. Adjust **`DATABASE_PATH`** if your SQLite file lives elsewhere. If **`sort=likes`** is unsupported in your build, replace **S2** with another non-default sort your router accepts.
