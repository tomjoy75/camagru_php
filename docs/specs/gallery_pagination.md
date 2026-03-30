# Feature: Gallery pagination

## Goal

Keep the public gallery usable as the number of published images grows by showing **a fixed maximum number of images per page** (at least **5** per page, per product scope), **newest first**, with **clear navigation** to move between pages—without adding detail views, comments, likes, or auth.

## Behavior

- **`GET /gallery`** continues to show the public, read-only gallery (no login).
- Images remain ordered by **`created_at` descending** (same as today).
- Only images for the **current page** are loaded and rendered (not the full table on every request).
- **Page size** is a small, documented constant (minimum **5** images per page; exact default can be 5 or higher if documented).
- **Page selection** is driven by a **query parameter** (e.g. `?page=2`) or an equivalent explicit, bookmarkable mechanism; **invalid or out-of-range** page values are handled safely (e.g. clamp to first/last valid page or show first page—behavior documented at implementation).
- The view shows **pagination controls** (e.g. “Previous” / “Next” and/or page numbers) so users can move between pages when more than one page exists.
- **Empty gallery**: still **200**, empty state, no pagination chrome or neutral chrome as appropriate.
- **Single page** (total images ≤ page size): gallery works as today functionally; pagination UI may be minimal or hidden.
- **DB load failure**: keep the same **safe, generic** user-facing handling as the listing feature (no SQL or internal paths exposed).

## Constraints

- **Architecture:** Controllers handle HTTP (including reading/sanitizing page input); repositories execute **parameterized** SQL only; views render HTML only (no DB).
- **Stack:** PHP standard library only; no new frameworks or JS requirements beyond what the project already uses for the gallery.
- **Security:** Treat page index as **untrusted input**; validate as integers within a sane range; escape all output in views (`htmlspecialchars` for URLs/text).
- **Out of scope for this feature:** per-image detail URLs, comments, likes, filters, sorting other than newest-first, editor changes, changing how `image_path` is stored.

## Success Criteria

- With **more than `page_size` images** in `images`, **`GET /gallery`** shows only **`page_size`** thumbnails and controls allow reaching **all** images across pages with correct ordering.
- With **0 images**, the page returns **200** and shows the **empty state** without errors.
- With **total images ≤ `page_size`**, all images appear on **one** page; no broken layout or server errors.
- **Invalid `page` values** do not cause uncaught errors or internal leakage; behavior matches the chosen rule (documented in implementation plan).
- Manual or scripted checks: first page shows the **newest** batch; last page shows the **oldest** remaining items; navigation from last page does not expose duplicate rows across pages.

## Implementation Plan

1. Add `ImageRepository::countForPublicGallery(): int` (single `COUNT(*)` on `images`).
2. Add `ImageRepository::findPageForPublicGallery(int $limit, int $offset): array` (`ORDER BY created_at DESC`, parameterized `LIMIT`/`OFFSET`).
3. In `GalleryController::show()`, define page size (≥5), read `$_GET['page']`, normalize to int ≥1, compute total pages and clamp current page, then fetch the page of rows (reuse existing error path on DB failure).
4. Pass `images`, `currentPage`, `totalPages`, and `pageSize` into the gallery view (existing `extract`/layout wiring).
5. Update `gallery.php`: keep grid and empty/error states; add prev/next (or equivalent) links using `?page=` only when `totalPages > 1`; escape hrefs.

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

**Test cases**

**Success**

- **S1:** `GET /gallery` (no query) → **200**, HTML. **Checks:** response body contains `Gallery` heading; if the gallery is empty, body contains `No images published yet.`; if there are persisted images with files on disk, count thumbnails by counting occurrences of **`alt="Published image"`** in the HTML — expect **≤ 5** (current `page_size`).
- **S2:** `GET /gallery?page=1` → **200**. **Check:** with unchanged DB and files, response body must be **byte-identical** to `GET /gallery` (e.g. `cmp` on two `curl -s` outputs from the same host).
- **S3:** Requires **more than `page_size`** rows in `images` with files present under `public/` (same as today’s editor upload layout). **`GET /gallery?page=2`** → **200**. **Checks:** (a) `alt="Published image"` count on page 1 and on page 2 is each **≤ `page_size`**; (b) no duplicate `src="…"` values between page 1 and page 2 (extract `src="/uploads/…"` or full `src=` from each page, combine, `sort | uniq -d` must be **empty**); (c) response HTML includes pagination chrome (`aria-label="Gallery pagination"` or visible Previous/Next for `totalPages > 1`).

**Failure**

- **F1:** `GET /gallerie` (or another unknown path) → **404** (unchanged router behavior).
- **F2 (manual only):** With the app otherwise working, simulate a **DB connection failure** (e.g. wrong DSN, stopped DB, or temporary change in dev only — revert after). **`GET /gallery`** → **200**, body shows the generic load message (e.g. `could not be loaded` / project’s exact copy), **no** SQL message or stack trace in the HTML.

**Edge**

- **E1:** `GET /gallery?page=0`, `?page=-1`, `?page=abc`, or `?page=` (empty) → **200**, no 500; page index treated safely (default **1** or clamp per implementation).
- **E2:** `GET /gallery?page=999999` (beyond last page) → **200**, no 500; body reflects **last valid page** (implementation clamps to last page).
- **E3:** **Precondition:** total rows in `images` with on-disk files is **≤ `page_size`** (one page only). **`GET /gallery`** → **200**. **Check:** no pagination `<nav>` for the gallery (e.g. HTML must **not** contain `aria-label="Gallery pagination"`); all visible thumbnails appear on that single response.

**Execute tests**

```bash
BASE=http://localhost:8080

# S1: status + optional thumbnail count (adjust expected max if page_size changes in code)
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery"
# Expect: 200

# S1 (content): thumbnail count should be ≤ 5 when images exist (0 when empty)
# curl -s "$BASE/gallery" | grep -o 'alt="Published image"' | wc -l

# S2: same body as default gallery when page=1
curl -s "$BASE/gallery" -o /tmp/gallery_default.html
curl -s "$BASE/gallery?page=1" -o /tmp/gallery_page1.html
cmp -s /tmp/gallery_default.html /tmp/gallery_page1.html && echo "S2 OK: bodies match" || echo "S2 FAIL: bodies differ"
rm -f /tmp/gallery_default.html /tmp/gallery_page1.html

# S3: requires >5 images with files on disk — uncomment and run when data is ready
# curl -s "$BASE/gallery" | grep -o 'alt="Published image"' | wc -l    # expect ≤ 5
# curl -s "$BASE/gallery?page=2" | grep -o 'alt="Published image"' | wc -l # expect ≤ 5
# curl -s "$BASE/gallery" | grep -Eo 'src="[^"]+"' | sort -u > /tmp/p1.src
# curl -s "$BASE/gallery?page=2" | grep -Eo 'src="[^"]+"' | sort -u > /tmp/p2.src
# sort /tmp/p1.src /tmp/p2.src | uniq -d   # expect: no lines (no shared src between pages)
# curl -s "$BASE/gallery" | grep -q 'aria-label="Gallery pagination"' && echo "S3 nav present" || echo "S3 nav missing (unexpected if totalPages>1)"

# F1: unknown path
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallerie"
# Expect: 404

# F2: manual — see test case F2 (DB failure, generic message, no stack trace)

# E1 / E2: bad page values still 200
for q in 'page=0' 'page=-1' 'page=abc' 'page=' 'page=999999'; do
  curl -s -o /dev/null -w "$q -> %{http_code}\n" "$BASE/gallery?$q"
done
# Expect: each line ends with -> 200

# E3: run when DB has ≤5 visible images (all on one page); expect no gallery pagination nav
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery"
# Expect: 200
# curl -s "$BASE/gallery" | grep -F 'aria-label="Gallery pagination"' && echo "E3 FAIL: nav should be absent" || echo "E3 OK: no pagination nav"
```
