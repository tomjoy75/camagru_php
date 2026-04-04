# Feature: Gallery image details

## Goal

Provide a **public, read-only** page for **one** saved image so visitors can see the full picture together with **who published it**, **when**, and a **summary of engagement** (like count and comment count). This completes the next slice of the gallery module after listing and pagination ([GitHub #24](https://github.com/tomjoy75/camagru_php/issues/24)), and matches `docs/feature_tree.md` (image, author, creation date, like count, comments summary).

## Behavior

- A user opens a **GET** URL that identifies a single gallery image by **numeric id** (exact path to be chosen to match existing routing style, e.g. under `/gallery/...`).
- The page is **readable without login** (same visibility as the public gallery list).
- The page shows:
  - the **image** via the stored `image_path` (only if the file resolves safely under `public/`, using the same path-safety idea as the listing: no `..`, must be a real file under the public root);
  - the **author’s username** (from `users` via `images.user_id`);
  - the image’s **`created_at`** in a human-readable form;
  - **like count**: total number of rows in `likes` for that `image_id` (may be **0**);
  - **comments summary**: at minimum the **total number of comments** for that `image_id` (may be **0**), with a clear empty state when the count is zero.
- If the **id is missing, not a positive integer, or no matching row** in `images`, respond with **404** (or the project’s standard “not found” handling).
- If the **database row exists but the file is missing** on disk, respond with **404** (or a single safe error page without leaking internal paths).
- The **gallery list** (and optionally each thumbnail) should offer a **link** to this detail page for discoverability.
- **No** like/unlike buttons, comment forms, or delete actions on this page (those belong to later issues).

## Constraints

- **MVC**: routing → controller loads data → view renders HTML only; repositories/services own SQL; **no HTML in controllers**.
- **PHP**: standard library only; **prepared statements** for all SQL; no new external dependencies.
- **Security**: validate and cast the image id server-side; **escape** all dynamic text in the view with `htmlspecialchars` (username, dates, counts, URLs as needed).
- **Data**: use existing tables `images`, `users`, `likes`, `comments`; read-only **SELECT** / aggregates only for this feature.
- **Scope boundary**: do **not** implement the full comment thread UI here—that is **[Comments] Comment listing per image** (#30). This spec allows **counts only** for “comments summary” to avoid duplicate work; a one-line teaser of the latest comment is optional only if it stays minimal and does not replace #30’s list behavior.

## Success Criteria

- **S1:** `GET` detail URL with a valid id for an existing on-disk image → **200**, page shows image, **correct** username, **correct** `created_at`, like count and comment count matching the database (including **0 / 0** when empty).
- **S2:** Unknown id, invalid id (`0`, negative, non-numeric), or missing file on disk → **404** (or documented equivalent), **no** SQL errors or stack traces to the client.
- **S3:** Unauthenticated request → same as authenticated; page remains **public**.
- **S4:** From `/gallery`, user can follow a link to the detail page and return to the list without broken navigation.
- **S5:** DB failure during load → safe behavior consistent with `GalleryController::show()` (e.g. generic in-page message without technical details), **no** uncaught exceptions.

## Implementation Plan

1. register `GET` route and `GalleryController` action for gallery image detail
2. parse and validate image id; **404** if missing, non-numeric, or not positive
3. add repository read for one image with username, `created_at`, like count, comment count
4. apply public `image_path` realpath guard; **404** if row missing or file not under `public/`
5. add detail view (`gallery_image.php`): image, escaped metadata, counts, back link to `/gallery`
6. link each item on `gallery.php` to the detail URL

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

### Test cases

**Success**

- **S1:** `GET` detail URL with a valid `id` and file present under `public/` → **200**; body includes the image (`<img`), **Back to gallery** link, **Author:** / **Published:** / **Likes:** / **Comments:** (counts may be **0 / 0**).
- **S3:** Same request **without** session cookie → **200** (public page).
- **S4:** `GET /gallery` HTML contains at least one link to the detail URL pattern for a listed image; detail page links back to `/gallery`.

**Failure**

- **F1:** Missing `id` (`/gallery/image` with no query, or `?id=` empty) → **404**.
- **F2:** Invalid `id`: non-numeric, zero, negative → **404**.
- **F3:** Numeric `id` with no row in `images` → **404**.
- **F4:** Row exists but file missing on disk or path fails the public `realpath` guard → **404**; response must not expose SQL or internal paths.

**Edge**

- **E1:** Very large positive `id` (no row) → **404**, no uncaught exception.
- **E2:** Wrong HTTP method on the detail route (e.g. `POST`) → **404** or safe rejection per router convention, no mutation.
- **E3:** DB unavailable / query throws → behavior matches `GalleryController::show()` (**200** with generic in-page message, no stack trace); *verify manually or in a controlled env.*

### Test Setup

No authentication. Set **`BASE`** to your running app (port/host). **`KNOWN_GOOD_ID`** must be an `id` from `images` with a file under `public/`. The script **auto-detects** the first id from `href="...gallery/image?id=..."` on `/gallery`; override if needed: `export KNOWN_GOOD_ID=24`.

**Assertion note:** Every layout page includes a header link to `/gallery`, so **`grep '/gallery'` is not enough** to prove the detail view loaded—use strings from `gallery_image.php` (e.g. **Back to gallery**, **Author:**).

**Runnable URL:** `GET /gallery/image?id=<int>` (adjust the script if routing changes).

### Execute tests

```bash
BASE=http://localhost:8080
# Auto-detect first detail id from listing; override: export KNOWN_GOOD_ID=24
KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -s "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}
if [ -z "$KNOWN_GOOD_ID" ]; then
  echo "SKIP S1/S3 body checks: no gallery/image links (empty gallery or wrong BASE). Publish an image or set KNOWN_GOOD_ID."
else
  # S1 / S3 — expect 200 (public; no cookie)
  code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
  echo "S1/S3 detail HTTP -> $code (expect 200)"
  [ "$code" = "200" ] || echo "FAIL: expected 200 for valid id=$KNOWN_GOOD_ID"

  html=$(curl -s "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
  echo "$html" | grep -q '<img' || echo "FAIL: expected <img (detail image)"
  echo "$html" | grep -q 'Back to gallery' || echo "FAIL: expected Back to gallery link"
  echo "$html" | grep -q 'Author:' || echo "FAIL: expected Author label"
  echo "$html" | grep -q 'Likes:' || echo "FAIL: expected Likes label"
  echo "$html" | grep -q 'Comments:' || echo "FAIL: expected Comments label"
fi

# F1 — missing id
curl -s -o /dev/null -w "F1 no query -> %{http_code}\n" "$BASE/gallery/image"
# F1 — empty id
curl -s -o /dev/null -w "F1 id empty -> %{http_code}\n" "$BASE/gallery/image?id="

# F2 — invalid ids → expect 404 each
for id in 0 -1 abc; do
  curl -s -o /dev/null -w "F2 id=$id -> %{http_code}\n" "$BASE/gallery/image?id=$id"
done

# F3 / E1 — unknown id (adjust if 999999999 exists in your DB)
curl -s -o /dev/null -w "F3/E1 unknown id -> %{http_code}\n" "$BASE/gallery/image?id=999999999"

# S4 — list exposes detail links
curl -s "$BASE/gallery" | grep -q 'gallery/image' || echo "FAIL: list should contain gallery/image links"

# S4 — detail back link (only if we have a good id)
if [ -n "$KNOWN_GOOD_ID" ]; then
  curl -s "$BASE/gallery/image?id=$KNOWN_GOOD_ID" | grep -q 'Back to gallery' || echo "FAIL: detail should link back to gallery (copy)"
fi

# E2 — wrong method (expect 404 here; id irrelevant)
curl -s -o /dev/null -w "E2 POST -> %{http_code}\n" -X POST "$BASE/gallery/image?id=1"
```

**F4:** manual — keep a DB row, delete or rename the file under `public/`, `GET` detail → **404**, body must not leak SQL/paths.

**E3:** manual — break DB or connection; expect **200** and copy like *could not be loaded* (same spirit as gallery list error), no stack trace.