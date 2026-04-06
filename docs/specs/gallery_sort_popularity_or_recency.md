# Feature: Gallery — sort by popularity or recency

## Goal

Let visitors change how the **public gallery** (`GET /gallery`) orders images: keep today’s default (**newest first**), add **oldest first** for recency, and add **popularity** ordering by **like count** and by **comment count**, while staying **read-only**, **paginated**, and **composable** with the existing **`user_id`** filter and **`page`** query parameter.

## Behavior

- **`GET /gallery`** with **no sort parameter** behaves as today: all images (or filtered set when `user_id` is valid), **`created_at` descending** (newest first), same page size and pagination rules.
- A **bookmarkable query parameter** selects the sort mode (implementation choice documented in code; recommended: **`sort`** with a **small whitelist** of string tokens).
  - **`newest`** (or **omitted** / empty): same as default — **`created_at` DESC**; tie-break **`images.id` DESC** (or equivalent stable ordering).
  - **`oldest`**: **`created_at` ASC**; tie-break **`images.id` ASC**.
  - **`likes`**: order by **like count descending** (same aggregate rule as today’s list: count of rows in **`likes`** per **`image_id`**); tie-break **`created_at` DESC**, then **`id` DESC** so order stays deterministic when counts match.
  - **`comments`**: order by **comment count descending** (count of rows in **`comments`** per **`image_id`**); tie-break **`created_at` DESC**, then **`id` DESC**.
- **Invalid `sort` values** (unknown token, wrong type): **ignore** and use the **default** (`newest`), **200** — same spirit as invalid `user_id` falling back to the full gallery.
- **Pagination** applies **after** the chosen ordering: **`totalCount`**, **`offset`**, and **`totalPages`** match the **same filtered row set** as today (respecting optional **`user_id`**). **`page`** clamping stays aligned with existing gallery behavior.
- **Pagination links** and any **sort controls** must preserve **both** active **`user_id`** (when set) **and** active **`sort`** so bookmarks and multi-page browsing stay stable.
- **Empty gallery** states (global vs filtered-by-user) stay consistent with existing copy and rules from the filter-by-user feature; changing sort does not change **404** semantics for the route itself.
- **Optional UX**: simple **GET**-based controls only (e.g. links or a form with `method="get"`) to pick sort mode; no requirement for JavaScript on the read path.

## Constraints

- **MVC:** Controller parses and normalizes **`$_GET`** (`sort`, existing `page` / `user_id`); repository exposes **parameterized SQL** only for list + count with optional user filter **and** sort mode; views emit HTML only and **escape** labels, URLs, and attributes (`htmlspecialchars`).
- **Stack:** PHP standard library only; no frameworks; vanilla JS optional for UI polish only.
- **Security:** Treat query parameters as **untrusted**; **whitelist** sort tokens in PHP; never concatenate raw input into SQL; no new mutating routes; no exposure of stack traces or internal paths on errors.
- **Consistency with list data:** Like and comment aggregates used for ordering must match the **same definitions** already used on the gallery list / detail (per-image counts from **`likes`** and **`comments`**).
- **Missing files:** If the controller continues to **drop rows** whose files are missing from disk (`filterRowsWithExistingFiles`), document that **page size may be short** on some pages and **total row count** may not match visible tiles; prefer documenting the current interaction over silent behavior drift.
- **Out of scope:** full-text search, admin-only sorts, API/JSON-only endpoints, changing editor save behavior, **#34** AJAX like UI, arbitrary raw `ORDER BY` from the client.

## Success Criteria

- With **default** sort (no param or `newest`), **`GET /gallery`** matches **current** ordering and pagination (regression-safe).
- With **`sort=oldest`**, images appear **ascending by `created_at`** (verify on a DB with known timestamps); pagination preserves **`sort=oldest`** in **`href`s**.
- With **`sort=likes`**, images with **more likes** appear **before** images with fewer; **ties** resolve consistently (same tie-break for repeated requests).
- With **`sort=comments`**, ordering matches **comment count descending** with the same tie-break expectations.
- With **`user_id`** and **`sort`** both set, listing stays **restricted to that user** and order matches the chosen mode; pagination links include **both** query arguments.
- **Invalid `sort`** values yield **200** and **same result set and order** as default **`newest`** for the same `user_id` / `page`.
- **DB failure** on load follows the **same safe, generic** handling as the current gallery list (no SQL or internal details in HTML).

## Implementation Plan

1. parse and whitelist `sort` from `$_GET` in `GalleryController`; pass token to view data
2. extend `findPageForPublicGallery` with sort arg; add `ORDER BY` for `newest` and `oldest` with tie-breaks
3. add `ORDER BY` for `likes` and `comments` using same per-image count semantics as today
4. call updated `findPageForPublicGallery` from `GalleryController::show` with parsed sort
5. preserve `sort` in `gallery.php` pagination `href` query string with `user_id` and `page`
6. add GET sort controls in `gallery.php` with escaped URLs

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

### Test cases

**Success**

- **S1:** `GET /gallery` and `GET /gallery?sort=newest` → **200**; first-page `data-image-id` order matches SQLite `images` ordered **`created_at DESC`**, **`id DESC`** (limit = gallery page size).
- **S2:** `GET /gallery?sort=oldest` → **200**; first-page id order matches **`created_at ASC`**, **`id ASC`**.
- **S3:** `GET /gallery?sort=likes` → **200**; `data-like-count` on tiles is **non-increasing** in document order; two fetches yield the **same** id sequence.
- **S4:** `GET /gallery?sort=comments` → **200**; first-page id order matches SQLite by **comment count DESC**, then **`created_at DESC`**, **`id DESC`** (skip if there are **no** `comments` rows to discriminate).

**Failure**

- **F1:** `GET /gallery?sort=not_a_mode` → **200**; `data-image-id` sequence equals **`GET /gallery`** (no sort param) for the same `user_id` / `page`.
- **F2:** `GET /gallerys` (typo) → **404**.

**Edge**

- **E1:** With **`user_id`** filter active and **`totalPages > 1`**, `Next` / `Previous` `href`s include **`sort=…`** and **`user_id=…`**.
- **E2:** `GET /gallery?sort=oldest&page=999` stays **200** and clamps like today; pagination links still carry **`sort=oldest`**.

### Test Setup

```bash
BASE=http://localhost:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
PAGE_SIZE=6

# DOM order: gallery tiles only (data-image-id on list links)
gallery_ids() { curl -sS "$1" | grep -oE 'data-image-id="[0-9]+"' | sed 's/^data-image-id="//;s/"$//'; }

gallery_likes() { curl -sS "$1" | grep -oE 'data-like-count="[0-9]+"' | sed 's/^data-like-count="//;s/"$//'; }

http_200() { test "$(curl -sS -o /dev/null -w '%{http_code}' "$1")" = 200; }
```

### Execute tests

```bash
set -euo pipefail

# S1 + F1 baseline ids (omit if gallery empty)
IDS_DEFAULT=$(gallery_ids "$BASE/gallery") || true
http_200 "$BASE/gallery"
http_200 "$BASE/gallery?sort=newest"
IDS_NEWEST=$(gallery_ids "$BASE/gallery?sort=newest")
test "$IDS_DEFAULT" = "$IDS_NEWEST"

# S2 oldest vs SQLite (first page; compare only tiles returned)
EXP_OLD=($(sqlite3 "$DATABASE_PATH" "SELECT id FROM images ORDER BY created_at ASC, id ASC LIMIT $PAGE_SIZE;"))
ACT_OLD=($(gallery_ids "$BASE/gallery?sort=oldest"))
for i in "${!ACT_OLD[@]}"; do test "${ACT_OLD[$i]}" = "${EXP_OLD[$i]}"; done

# S3 likes: non-increasing counts, stable ids
http_200 "$BASE/gallery?sort=likes"
A1=$(gallery_ids "$BASE/gallery?sort=likes" | tr '\n' ' ')
A2=$(gallery_ids "$BASE/gallery?sort=likes" | tr '\n' ' ')
test "$A1" = "$A2"
prev=999999999
while read -r c; do test "$c" -le "$prev"; prev=$c; done < <(gallery_likes "$BASE/gallery?sort=likes")

# S4 comments order (skip when comment counts do not tie-break)
COMMENT_ROWS=$(sqlite3 "$DATABASE_PATH" "SELECT COUNT(*) FROM comments;")
if [ "${COMMENT_ROWS:-0}" -gt 0 ]; then
  EXP_COM=($(sqlite3 "$DATABASE_PATH" "SELECT i.id FROM images i LEFT JOIN (SELECT image_id, COUNT(*) AS c FROM comments GROUP BY image_id) x ON x.image_id = i.id ORDER BY COALESCE(x.c, 0) DESC, i.created_at DESC, i.id DESC LIMIT $PAGE_SIZE;"))
  ACT_COM=($(gallery_ids "$BASE/gallery?sort=comments"))
  for i in "${!ACT_COM[@]}"; do test "${ACT_COM[$i]}" = "${EXP_COM[$i]}"; done
fi

# F1 invalid sort matches default sequence
IDS_BAD=$(gallery_ids "$BASE/gallery?sort=not_a_mode")
test "$IDS_DEFAULT" = "$IDS_BAD"

# F2 typo path
test "$(curl -sS -o /dev/null -w '%{http_code}' "$BASE/gallerys")" = 404

# E1 pagination preserves user_id + sort (needs OWNER_ID with > PAGE_SIZE images)
OWNER_ID=$(sqlite3 "$DATABASE_PATH" "SELECT user_id FROM images GROUP BY user_id HAVING COUNT(*) > $PAGE_SIZE LIMIT 1;") || true
if [ -n "${OWNER_ID:-}" ]; then
  BODY=$(curl -sS "$BASE/gallery?user_id=$OWNER_ID&sort=oldest&page=1")
  echo "$BODY" | grep -q "user_id=$OWNER_ID" && echo "$BODY" | grep -q "sort=oldest"
fi

# E2 clamp + sort still present in page (pagination or sort links)
BODY=$(curl -sS "$BASE/gallery?sort=oldest&page=999")
http_200 "$BASE/gallery?sort=oldest&page=999"
echo "$BODY" | grep -q "sort=oldest"
```

Notes:

- Run with **`bash`** from repo root; server at **`BASE`**; **non-empty** gallery needed for **S1–S4** and **F1**.
- If **`filterRowsWithExistingFiles`** drops missing files, **S1–S4** may diverge from SQLite until files exist; seed images or relax checks to a prefix match.
- **E1** runs only when SQLite returns an **`OWNER_ID`** with more than **`PAGE_SIZE`** images.
