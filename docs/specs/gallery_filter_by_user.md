# Feature: Gallery — filter by user

## Goal

Let visitors browse the **public gallery** restricted to **images published by a single user**, while keeping the page **read-only**, **newest-first**, and **composable with existing pagination** (`?page=`). This completes the “filter by user” subfeature from the product feature tree without adding comments, likes, or new write routes.

## Behavior

- **`GET /gallery`** with **no user filter** behaves exactly as today: all images, paginated, newest first.
- A **bookmarkable query parameter** selects the author (implementation choice documented in code, e.g. **`user_id`** with a **positive integer** matching `images.user_id`). Only images whose `user_id` equals that value are counted and listed; ordering remains **`created_at` DESC**.
- **Pagination** applies **after** the filter: total pages and `offset`/`limit` are computed from the **filtered** row set. **`?page=`** semantics (clamp invalid values, same page size as today) stay aligned with the existing pagination feature.
- **Filter cleared**: a control (e.g. “All users” / link to `/gallery` without the filter param) shows the full gallery again.
- **Invalid `user_id` formats** (missing, empty, non-numeric, zero, or negative): **ignore the filter** and show the **full** gallery, same as omitting `user_id` (**200**).
- **Positive integer `user_id`:** filter is **on**. If there is **no** row in **`users`** with that `id`, **or** the user has **no** matching rows in **`images`**, the response is still **200** with the **filtered** empty copy — **not** **404**, and **not** a fallback to the unfiltered gallery (distinct from the global “no images yet” empty state when other users have images).
- **Optional UX**: a simple way to pick a user (e.g. dropdown populated from distinct gallery authors, or links from image detail using the displayed username / id). Exact control is up to implementation as long as it only issues safe `GET` requests and escapes output.
- **Detail and list links**: tiles should still link to **`GET /gallery/image?id=…`**; filtered gallery navigation must preserve the filter in pagination links (e.g. `?user_id=…&page=2`) so behavior is stable when bookmarked.

## Constraints

- **MVC:** Controller reads and normalizes `$_GET`; repository runs **parameterized SQL** only (`WHERE` on `user_id` when filtering); views emit HTML only and **escape** all text and attribute values (`htmlspecialchars`).
- **Stack:** PHP standard library only; no new frameworks; vanilla JS optional and minimal if used for UI only (no required JS for the core read path).
- **Security:** Treat all query parameters as **untrusted**; validate **`user_id`** so invalid shapes (empty, non-numeric, `<= 0`) drop the filter, while a valid **positive** integer keeps filtering even when **`users`** has no such id (empty filtered result, **200** only); never concatenate raw input into SQL; do not expose internal paths or stack traces on errors.
- **Privacy:** Only **published** gallery data—same as today (images already public). No leakage of email or other profile fields unless already shown elsewhere by product choice.
- **Out of scope:** sort/filter by popularity or recency (#26), search, admin-only filters, mutating routes, changing how images are saved in the editor.

## Success Criteria

- With the filter set to a user who has **at least one** image, the gallery lists **only** that user’s images (verify by comparing `user_id` on listed items or DB spot-check), **newest first**, with correct thumbnails and existing detail links.
- With the filter set and **more than one page** of that user’s images, **`?page=`** shows disjoint pages (no duplicate image ids across pages) and pagination controls preserve the filter.
- With the filter set and **no** images for that user (or unknown id), response is **200** with an appropriate empty state and **no** uncaught exceptions.
- With **invalid** filter parameters, the page matches the **unfiltered** gallery (full catalog, same pagination rules as today).
- **`GET /gallery`** without filter remains **backward compatible** with existing automated/manual checks for listing + pagination.
- DB failure on load follows the **same safe, generic** handling as the current gallery list (no SQL or internal details in HTML).

## Implementation Plan

1. parse and normalize optional `user_id` from `$_GET` in gallery controller
2. add filtered `countForPublicGallery(?int $userId)` in image repository
3. add filtered `findPageForPublicGallery` in image repository
4. wire filtered count/offset and pass filter into view from controller
5. preserve `user_id` in pagination hrefs and add clear-filter link in gallery view
6. branch empty-state message for filtered vs unfiltered gallery in gallery view

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

### Test cases

**Success**

- **S1:** `GET /gallery?user_id=<owner>` → **200**; every `data-image-id` on the page is an `images.id` row with that `user_id` (spot-check via `sqlite3`).
- **S2:** With filter active and `totalPages > 1`, pagination links include the same `user_id` query argument as the current view (e.g. Next/Previous `href` contains `user_id=<owner>`).
- **S3:** Clear-filter control: page offers a safe link to `GET /gallery` with **no** `user_id` (full gallery).

**Failure**

- **F1:** Unknown path (e.g. typo) → **404**; router behavior unchanged.
- **F2:** `GET /gallery?user_id=abc` (or `0`, `-1`, empty) → **200**; response body matches `GET /gallery` with the same other query params omitted (byte match or same thumbnail/`data-image-id` set as unfiltered).

**Edge**

- **E1:** `user_id` valid integer but user has **no** images (or no such `users.id`) while the gallery is **not** globally empty → **200**; body contains the **filtered** empty copy (**implementations must use the exact sentence:** `No published images for this user.` so this check stays greppable).
- **E2:** Filtered `?user_id=<owner>&page=2` with more than one page of that user’s images → **200**; no image `src` appears on both page 1 and page 2 (same technique as gallery pagination spec: extract `src="…"`, `sort`, `uniq -d` empty).
- **E3:** Global gallery empty → **200**; `?user_id=<anything valid or invalid>` still **200** and still shows the **global** empty copy (`No images published yet.`) when the filter is ignored; when filter is valid but no rows, **E1** copy applies if the DB has images from others.

### Test Setup

Run from the **repository root** with the app reachable at `BASE` and the same SQLite file the app uses (`database/camagru.db` by default). No login cookie is required.

```bash
BASE=http://localhost:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}

# Owner id with at least one image (skip S1/E2 if this prints nothing)
OWNER_ID=$(sqlite3 "$DATABASE_PATH" "SELECT user_id FROM images ORDER BY id LIMIT 1;")
```

### Execute tests

```bash
BASE=http://localhost:8080
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}

# S1: 200; every data-image-id belongs to OWNER_ID (skip if no images)
OWNER_ID=$(sqlite3 "$DATABASE_PATH" "SELECT user_id FROM images ORDER BY id LIMIT 1;")
if [ -n "$OWNER_ID" ]; then
  curl -s -o /dev/null -w "S1: %{http_code}\n" "$BASE/gallery?user_id=$OWNER_ID"
  HTML=$(curl -s "$BASE/gallery?user_id=$OWNER_ID")
  for ID in $(echo "$HTML" | grep -oE 'data-image-id="[0-9]+"' | grep -oE '[0-9]+' | sort -u); do
    sqlite3 "$DATABASE_PATH" "SELECT 1 FROM images WHERE id=$ID AND user_id=$OWNER_ID;" | grep -q 1 \
      || echo "S1 FAIL: id $ID not owned by $OWNER_ID"
  done
fi

# S2 / S3 (manual / when multi-page): inspect HTML — pagination hrefs keep user_id=…; clear filter uses href="/gallery" (no user_id).

# F1: typo path → 404
curl -s -o /dev/null -w "F1: %{http_code}\n" "$BASE/gallerie"

# F2: invalid user_id → same body as unfiltered
curl -s "$BASE/gallery" -o /tmp/gu.html
curl -s "$BASE/gallery?user_id=abc" -o /tmp/gb.html
cmp -s /tmp/gu.html /tmp/gb.html && echo "F2 OK" || echo "F2 FAIL"
rm -f /tmp/gu.html /tmp/gb.html

# E1: huge user id, global gallery non-empty → filtered empty copy
# GHOST_UID=$(sqlite3 "$DATABASE_PATH" "SELECT COALESCE(MAX(id),0)+999999 FROM users;")
# curl -s "$BASE/gallery?user_id=$GHOST_UID" | grep -q 'No published images for this user.' && echo "E1 OK"

# E2: filtered page 1 vs 2, no duplicate src (needs >6 images for that user)
# curl -s "$BASE/gallery?user_id=$OWNER_ID" | grep -Eo 'src="[^"]+"' | sort -u > /tmp/gp1.src
# curl -s "$BASE/gallery?user_id=$OWNER_ID&page=2" | grep -Eo 'src="[^"]+"' | sort -u > /tmp/gp2.src
# sort /tmp/gp1.src /tmp/gp2.src | uniq -d | grep . && echo "E2 FAIL" || echo "E2 OK"
```

**Run:** `bash` from repo root with the PHP server up. **PHPUnit:** not used here; use **curl + sqlite3** only. Align the **E1** string with the view copy above.
