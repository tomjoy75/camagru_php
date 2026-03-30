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

- **S1:** `GET /gallery` (no query) → **200**, HTML; when images exist, shows first page only (at most `page_size` thumbnails after implementation); when none, empty state.
- **S2:** `GET /gallery?page=1` → **200**, same effective content as S1 for a populated gallery.
- **S3:** With more than `page_size` DB rows whose files exist under `public/`, `GET /gallery?page=2` → **200** and shows a **different** set of images than page 1 (no duplicates across 1 vs 2); prev/next (or equivalent) links appear when `totalPages > 1`.

**Failure**

- **F1:** `GET /gallerie` or other unknown path → **404** (unchanged router behavior).

**Edge**

- **E1:** `GET /gallery?page=0`, `?page=-1`, `?page=abc`, or `?page=` (empty) → **200**, no 500; page index treated safely (e.g. default or clamp per implementation plan).
- **E2:** `GET /gallery?page=999999` (beyond last page) → **200**, no 500; behavior matches clamp rule (e.g. last valid page or first).
- **E3:** Total images ≤ `page_size` → **200**, all visible images on one request; pagination chrome absent or minimal (per spec).

**Execute tests**

```bash
BASE=http://localhost:8080

# S1: default gallery
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery"
# Expect: 200

# S2: explicit page 1
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery?page=1"
# Expect: 200

# S3: compare thumbnail counts / overlap (requires > page_size images with files on disk)
# curl -s "$BASE/gallery" | grep -o '<img' | wc -l
# curl -s "$BASE/gallery?page=2" | grep -o '<img' | wc -l
# Expect: each ≤ page_size; after implementation, sum across pages matches filterRowsWithExistingFiles-visible total (manual if needed)

# F1: unknown path
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallerie"
# Expect: 404

# E1 / E2: bad page values still 200
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery?page=0"
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery?page=-1"
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery?page=abc"
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery?page="
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery?page=999999"
# Expect: all 200 (no uncaught errors)

# E3: small corpus — optional visual check in browser
```
