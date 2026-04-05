# Feature: Gallery like count

## Goal

Show an accurate **aggregate like count** for each **published gallery image** everywhere browsing users expect it, matching `docs/feature_tree.md` (likes per image in the gallery and/or image view). This implements [GitHub #33](https://github.com/tomjoy75/camagru_php/issues/33) and completes the read side of likes together with [GitHub #32](https://github.com/tomjoy75/camagru_php/issues/32) (toggle). The **gallery listing** (`GET /gallery`, including paginated pages) is the main gap today; the **image detail** page already displays a count and must stay **consistent** with the same definition (`COUNT(*)` of `likes` rows per `image_id`).

## Behavior

- **Definition:** For each `images.id`, the displayed number is the count of rows in `likes` with that `image_id`. **No likes → 0** (show zero explicitly, not an empty omission).
- **Gallery list:** Each item on the public gallery grid (per page) shows that image’s like count in a clear, accessible way (e.g. label or badge near the thumbnail or under it). Counts are **read-only** on this page; toggling likes remains on the detail flow from `docs/specs/gallery_like_toggle.md`.
- **List markup contract (tests + accessibility):** On the **same** opening `<a …>` that links to `/gallery/image?id=<id>`, include **both** visible text **`Likes:`** immediately followed by the integer count (inside the anchor is fine) **and** machine-readable attributes **`data-image-id="<id>"`** and **`data-like-count="<n>"`** (`n` non‑negative integer, same value as the visible count). Attribute order on the tag is unrestricted. This keeps curl-based checks stable without depending on fragile `sed` ranges.
- **Image detail:** The **Likes:** value on `GET /gallery/image?id=…` must use the **same** aggregate rule as the list so users never see conflicting numbers between list and detail for the same id.
- **Who sees it:** **Everyone** (guests and logged-in users). No mutation; no requirement to show *whether the current user* liked (that stays separate; see [GitHub #34](https://github.com/tomjoy75/camagru_php/issues/34) for richer live UI).
- **After toggles:** When a user likes or unlikes on the detail page and returns to the list (or refreshes), counts on **both** list and detail reflect the updated database state.

## Constraints

- **MVC:** Router → controller loads list/detail data; **repository** (or existing gallery read path) owns SQL aggregates; **views** render integers only, escaped as needed (`htmlspecialchars` for any surrounding text; numeric counts may be cast to int for display).
- **PHP:** Standard library only; **prepared statements** for all SQL; no new dependencies.
- **Security:** Counts are derived from server-side queries only; do not accept client-supplied counts. Reuse existing rules for which images appear in the public gallery (same as today’s listing/detail guards).
- **Data:** Use the existing `likes` table and its foreign keys as in `database/schema.sql`.
- **Performance:** Avoid an unbounded per-row query pattern if a simple batch or join keeps the list fast for typical homework scale; exact strategy is an implementation choice.

## Success Criteria

- **S1:** For an image with **N** rows in `likes`, the gallery **list** shows **N** for that item, and the **detail** page shows **N** in **Likes:**.
- **S2:** For an image with **no** likes, the list shows **0** (or equivalent explicit empty count) and detail shows **0**.
- **S3:** Guest **GET** `/gallery` and **GET** `/gallery/image?id=…` → **200** where applicable; counts visible without login.
- **S4:** After an authenticated **POST** like toggle (per `gallery_like_toggle.md`), **GET** list and **GET** detail for that `image_id` both show the **updated** count (±1 as appropriate).
- **S5:** No SQL errors or stack traces exposed to the client; DB failure behavior matches existing gallery error handling (e.g. generic load error on the list).

## Implementation Plan

1 add `like_count` to `ImageRepository::findPageForPublicGallery` via the same per-image `COUNT(*)` rule as `findPublicDetailById`
2 update the list-row phpdoc on `findPageForPublicGallery` to include `like_count`
3 render each item’s `like_count` in `gallery.php` (int cast, escaped label text **`Likes:`** + count, plus `data-image-id` / `data-like-count` on the detail `<a>` per Behavior)
4 test `GET /gallery`: parsed count for one `id` matches `GET /gallery/image?id=` **Likes:** for that `id`
5 test after `POST /gallery/like`: refreshed list and detail both show the updated count

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

### Test cases

**Success**

- **S1:** `GET /gallery` → **200**; HTML for each listed image includes that image’s like count (non-negative integer), `data-like-count` / `data-image-id` on the detail `<a>` per Behavior, and for a chosen `id` the count **matches** `GET /gallery/image?id=<id>` **Likes:**.
- **S2:** Image with no likes → list and detail both show **0**.

**Failure**

- **F1:** Gallery DB/load failure path → **200** with the same generic in-page message as `GalleryController::show()` today (e.g. gallery could not be loaded); response body must **not** contain leak patterns such as `PDOException`, `SQLSTATE`, `Fatal error`, `Uncaught`, or `Stack trace` (case-insensitive check in optional automation below).

**Edge**

- **E1:** Guest (no session) → list and detail counts **match** and are visible (**200**).
- **E2:** After **POST** `/gallery/like` (authenticated), refreshed list and detail for that `image_id` both reflect the new aggregate count.
- **E3:** Paginated `GET /gallery?page=2` (when `totalPages > 1`) → counts on that page still match detail per `id`.

### Test Setup (authentication)

```bash
rm -f cookies.txt
BASE=http://localhost:8080

STAMP=$(date +%s)
EMAIL="like_count_${STAMP}@example.com"
USER="liker${STAMP}"
PASS='Testpass1!'

curl -s -o /dev/null -c cookies.txt -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"
curl -s -o /dev/null -c cookies.txt -b cookies.txt -L -X POST "$BASE/login" \
  -d "email=$EMAIL" -d "password=$PASS"
```

### Execute tests

```bash
set -uo pipefail
BASE=http://localhost:8080
LIKE_POST="$BASE/gallery/like"
IMAGE_FIELD=image_id
FAILED=0

bump_fail() { echo "FAIL: $*"; FAILED=$((FAILED + 1)); }

detail_like_count() {
  curl -s "$BASE/gallery/image?id=$1" | tr '\n' ' ' \
    | sed -n 's/.*Likes:<\/dt>[[:space:]]*<dd[^>]*>\([0-9][0-9]*\).*/\1/p' | head -1
}

# Uses data-like-count on the same <a> as href="/gallery/image?id=<id>" (see Behavior; href before count or count before href)
# Optional $2 = gallery page number (default 1). E3 must pass the page where the id appears.
list_like_count() {
  id="$1"
  page="${2:-1}"
  if [ "$page" = "1" ]; then
    url="$BASE/gallery"
  else
    url="$BASE/gallery?page=$page"
  fi
  h=$(curl -s "$url" | tr -d '\n')
  v=$(echo "$h" | sed -n 's/.*<a[^>]*href="\/gallery\/image?id='"$id"'"[^>]*data-like-count="\([0-9][0-9]*\)"[^>]*>.*/\1/p')
  if [ -z "$v" ]; then
    v=$(echo "$h" | sed -n 's/.*<a[^>]*data-like-count="\([0-9][0-9]*\)"[^>]*href="\/gallery\/image?id='"$id"'"[^>]*>.*/\1/p')
  fi
  echo "$v"
}

# First published image on page 1 (override if empty)
KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -s "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}

if [ -z "$KNOWN_GOOD_ID" ]; then
  echo "SKIP: no gallery/image?id= on /gallery — publish an image first"
  exit 0
fi

# S1 / E1 — guest OK; list vs detail must match
echo "S1/E1 detail=$BASE/gallery/image?id=$KNOWN_GOOD_ID"
d=$(detail_like_count "$KNOWN_GOOD_ID")
l=$(list_like_count "$KNOWN_GOOD_ID")
echo "detail Likes=$d list data-like-count=$l (expect equal)"
if [ -z "$d" ] || [ -z "$l" ] || [ "$d" != "$l" ]; then
  bump_fail "S1/E1 list/detail mismatch or empty parse (implement data-like-count + href on same <a>)"
else
  echo OK
fi

# S2 — if detail is 0, list must be 0
if [ "$d" = "0" ] && [ "$l" != "0" ]; then
  bump_fail "S2 expected list 0 when detail 0"
fi

# E2 — toggle then recheck (needs cookies.txt from Test Setup); restore state with second POST
if [ -s cookies.txt ]; then
  curl -s -o /dev/null -b cookies.txt -X POST "$LIKE_POST" -d "${IMAGE_FIELD}=$KNOWN_GOOD_ID"
  d2=$(detail_like_count "$KNOWN_GOOD_ID")
  l2=$(list_like_count "$KNOWN_GOOD_ID")
  echo "E2 after POST like: detail=$d2 list=$l2"
  if [ -z "$d2" ] || [ -z "$l2" ] || [ "$d2" != "$l2" ]; then
    bump_fail "E2 list/detail mismatch after toggle"
  fi
  curl -s -o /dev/null -b cookies.txt -X POST "$LIKE_POST" -d "${IMAGE_FIELD}=$KNOWN_GOOD_ID"
else
  echo "SKIP E2: no cookies.txt (run Test Setup)"
fi

# E3 — second page (skip if only one page)
tp=$(curl -s "$BASE/gallery" | tr '\n' ' ' | sed -n 's/.*Page 1 of \([0-9][0-9]*\).*/\1/p' | head -1)
if [ -n "$tp" ] && [ "$tp" -gt 1 ] 2>/dev/null; then
  ID2=$(curl -s "$BASE/gallery?page=2" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)
  if [ -n "$ID2" ]; then
    d3=$(detail_like_count "$ID2")
    l3=$(list_like_count "$ID2" 2)
    echo "E3 page=2 id=$ID2 detail=$d3 list=$l3"
    if [ -z "$d3" ] || [ -z "$l3" ] || [ "$d3" != "$l3" ]; then
      bump_fail "E3 list/detail mismatch on page 2"
    fi
  fi
else
  echo "SKIP E3: single-page gallery"
fi

if [ "$FAILED" -gt 0 ]; then
  echo "$FAILED check(s) failed"
  exit 1
fi
echo "All automated checks passed"
```

### Execute tests — F1 optional (DB missing; local dev only)

**Requires:** app served from the project root so `database/camagru.db` is the file `Database.php` opens; **stop the PHP server** before moving the file, then restart so the PDO singleton is cleared.

```bash
set -euo pipefail
BASE=http://localhost:8080
DB=database/camagru.db
BK=/tmp/camagru_f1_$$

# Stop your PHP built-in server (or FPM pool) before this block; restart after restore.
test -f "$DB" || { echo "SKIP F1: no $DB"; exit 0; }
cp -a "$DB" "$BK"
rm -f "$DB"
# start server, then:
code=$(curl -s -o /tmp/gallery_f1.html -w "%{http_code}" "$BASE/gallery" || true)
body=$(cat /tmp/gallery_f1.html | tr '[:upper:]' '[:lower:]')
if echo "$body" | grep -qE 'pdoexception|sqlstate|fatal error|uncaught|stack trace'; then
  echo "FAIL F1: leak pattern in body"
  mv "$BK" "$DB"
  exit 1
fi
test "$code" = "200" || { echo "FAIL F1: expected HTTP 200, got $code"; mv "$BK" "$DB"; exit 1; }
echo "F1 OK (restore DB from backup and restart server)"
mv "$BK" "$DB"
# stop server; restart normally
```
