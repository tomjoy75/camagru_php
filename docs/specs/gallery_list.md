# Feature: Public gallery — listing (read-only)

## 1. Feature goal

Provide a **public, read-only** page that lists **all** images saved in the application (rows in `images`), **newest first**, so visitors can browse what users have published without logging in. This is the smallest vertical slice toward the full gallery module: **listing only**.

## 2. User-visible behavior

- A user opens **`GET /gallery`** (exact path may match existing routing conventions).
- The page shows a **simple grid or list** of every image referenced in the database, ordered by **`created_at` descending** (newest at the top).
- Each item displays the image using the stored **`image_path`** as a web path (e.g. `/uploads/...` when `image_path` is `uploads/...`).
- **No login** is required to view the gallery.
- If there are **no images**, the page still returns **200** and shows an **empty state** (short message), not an error.
- **No** per-image detail view, pagination controls, comment forms, like buttons, or delete actions on this page.

## 3. Constraints / out of scope

**In scope**

- Public read-only HTML page.
- Data from **`images`** table only (no scanning `public/uploads/` as the source of truth).
- Reasonable presentation (accessible `alt` text where applicable; escape output in views).

**Explicitly out of scope**

- Pagination (all rows in one page for this unit, or a fixed safe upper cap documented in implementation if needed for abuse — prefer “all” for smallest slice unless product requires a cap).
- Image detail page (single-image view, deep links, metadata beyond what’s needed for thumbnails).
- Comments, likes, notifications.
- Delete or any mutating action from this page.
- Filtering by user, search, or sort options other than newest-first.
- Changing how images are saved in the editor (reuse existing `image_path` convention).

## 4. Main components / modules involved

| Layer | Responsibility |
|--------|----------------|
| **Routing** | Register `GET /gallery` → gallery controller action. |
| **Controller** (e.g. `GalleryController`) | Load image list via repository; pass data to view; set response headers; **no HTML** in controller. |
| **Repository** (`ImageRepository` or equivalent) | New read method: list all images for public display, ordered by `created_at` DESC (e.g. `findAllForPublicGallery()`). |
| **View** | Render gallery layout section: loop over rows, emit `<img>` (and optional links only if they stay in-scope — default: no link to a detail page). |
| **Layout / nav** | Add a visible **Gallery** (or equivalent) link so the page is reachable from the rest of the site. |

Auth module: **not** required for this feature’s read path.

## 5. Minimal implementation plan

1. Add **`ImageRepository`** method to **SELECT** all rows from `images` **ORDER BY `created_at` DESC** (columns needed: at least `id`, `image_path`; optional `user_id` if displayed later — omit from UI for this slice).
2. Add **`GalleryController::show()`** (or equivalent): call repository, pass `$images` (or empty array) to the view.
3. Add **route** `GET /gallery` → that controller action.
4. Add **`gallery.php`** (or named view): grid/list, empty state, escaped paths for `src`/`alt`.
5. Update **layout** navigation to include a link to `/gallery`.

## 6. Test plan

### Test cases

**Success**

- **S1:** `GET /gallery` without session → **200**, HTML gallery page.
- **S2:** With at least one row in `images` and file present under `public/uploads/` → page shows that image (HTTP 200 for page; image loads via existing static/upload route).
- **S3:** With **zero** rows in `images` → **200**, empty state message, no server error.

**Failure / safety**

- **F1:** Invalid gallery URL (e.g. typo) → existing **404** behavior unchanged.
- **F2:** DB error when listing → safe error handling without exposing SQL or paths (exact behavior: generic message or 500 per project convention — document choice at implementation). **Implemented:** HTTP **200** with a short generic in-page message (no technical details).

**Edge**

- **E1:** Row exists but file missing on disk → broken image or skipped row; must not crash the page (pick one strategy in implementation, keep minimal). **Implemented:** skip the row (no `<img>` for that entry); page stays **200**.

### Execute tests (curl)

```bash
BASE=http://localhost:8080

# S1: public gallery without auth
curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery"
# Expect: 200

# S2 / S3: visual check in browser; DB state drives S2 vs S3
# After saving at least one image via editor (authenticated flow), reload /gallery and confirm thumbnail appears.

# E1: optional manual — remove one file from public/uploads/ for a DB row and confirm page still returns 200
```

### Test setup (optional)

No authentication required for **`GET /gallery`**. To populate **S2**, use the existing editor flow (register → login → upload → compose → save) or insert a test row + file in a dev environment consistent with **`image_path`** format (`uploads/...`).

---

## Implementation Plan

1. add `ImageRepository` method selecting all `images` ordered by `created_at` DESC  
2. add `GalleryController::show` calling repository and passing rows to the view  
3. register `GET /gallery` route to that action  
4. add `gallery.php` view: grid or list, empty state, escaped `src`/`alt` from `image_path`  
5. add Gallery link in `layout` navigation  

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

---

## Post-feature cleanup / tech debt (optional)

- If the table grows large, **pagination** (feature tree) should replace “all rows on one page.”
- **Author username** and **created date** on cards are natural follow-ups for a detail page or richer listing (out of scope here).
