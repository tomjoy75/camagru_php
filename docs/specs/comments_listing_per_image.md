# Feature: Comment listing per image

## Goal

On the **public gallery image detail** page, show an **ordered list of comments** for that image: each row displays the **commenter’s username**, the **comment text**, and a **human-readable timestamp**, matching `docs/feature_tree.md` (“Ordered by creation date; show commenter username and timestamp”) and [GitHub #30](https://github.com/tomjoy75/camagru_php/issues/30). This builds on **[Comments] Persistence** (#29) and **[Gallery] Image details** (#24): storage and the detail route already exist; this issue adds **read + render** only—**no** comment form, POST handling, or validation rules (#27/#28).

## Behavior

- **Where:** The same **GET** detail flow as today (e.g. `/gallery/image?id=<int>`): **authenticated and unauthenticated** visitors see the **same** comment list (public read, consistent with the rest of the detail page).
- **Order:** Comments for the requested `image_id` appear **oldest first** (`created_at` ascending, tie-break by `id` ascending if needed for stability).
- **Content per row:** **Username** from `users` (join on `comments.user_id`), **raw stored `content`** as display text (escaped in HTML per security rules), **`created_at`** shown in a clear human-readable form (same style family as the image’s published date on the same page if one exists).
- **Empty state:** When there are **no** comments, show a short, explicit empty state (e.g. “No comments yet”)—**do not** hide the section entirely unless the view already uses a single “Comments” block that includes the count; the existing **comment count** on the detail page should stay **consistent** with `COUNT(comments)` for that image.
- **Invalid / missing image:** If the controller already returns **404** for bad ids or missing files, **no** comment list is shown (unchanged). Do **not** query comments for ids that fail before the detail view is chosen.
- **Out of scope:** Comment **submission** (#27), **server-side validation** of new text (#28), **deletion/editing** (#31), AJAX refresh, pagination of comments (unless the spec is extended—default is **all** comments for the image; document if a soft cap is required for abuse).

## Constraints

- **MVC:** `CommentRepository` (or equivalent) holds **SQL only**; `GalleryController` (or the controller that renders image detail) loads the list and passes a simple structure to the view; the **view** outputs HTML only and **escapes** every dynamic string with `htmlspecialchars` (username, content, formatted dates).
- **PHP:** Standard library only; **prepared statements** for the list query (`image_id` bound as integer).
- **Security:** Never trust client input for “which comments”—only load comments for the **same** validated `image_id` already resolved for the detail page; escape all displayed fields; no SQL built from raw strings.
- **Performance:** One (or at most two) read queries for comments for this request is enough; avoid N+1 queries (use a JOIN or a single query returning username + content + created_at).
- **Consistency:** Match naming and patterns used by `GalleryController`, `ImageRepository`, and `CommentRepository::insert` / `imageExists`.

## Success Criteria

- **S1:** For an image with **zero** comments, the detail page **200** shows the comments section with an **empty state** and the header/summary **comment count** remains **0**.
- **S2:** For an image with **one or more** comments (rows in `comments` for that `image_id`), the detail page lists **all** of them **oldest first**, each with **correct** username, **escaped** visible text matching stored `content`, and a **visible** timestamp per row.
- **S3:** **Unauthenticated** `GET` still **200** and shows the **same** list as for a logged-in user viewing the same id (public gallery).
- **S4:** **404** cases for invalid/missing image detail are unchanged; no comment SQL runs in a way that leaks data for other images.
- **S5:** DB failure while loading comments follows the same **safe** behavior as other gallery detail partial failures (e.g. generic message, no stack trace to the client)—**or** comments section degrades gracefully as documented in the implementation if the project standard differs (state explicitly in the plan).

## Implementation Plan

1. add `CommentRepository::findByImageId(int $imageId): array` (or equivalent name) returning rows with username, content, `created_at` (and ids if useful), ordered oldest-first; return empty array for non-positive `imageId` or on handled DB failure
2. call the new repository method from the gallery image **detail** controller action after the image row is validated and loaded, passing the list into the view data structure
3. extend the gallery image **detail** view with a **Comments** subsection: loop rows, escape username/content/dates, show empty state when the list is empty
4. manually verify comment **count** at the top of the page still matches `COUNT(*)` for that `image_id` when comments exist (adjust query reuse if the count and list were duplicated unnecessarily)
5. run HTTP checks: valid id with 0 and with 2+ comments; invalid id still 404; unauthenticated GET sees comments

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

### Test cases

**Success**

- **S1:** `GET /gallery/image?id=<valid>` for an image with **no** comments → **200**; HTML includes the **Comments** summary (`Comments:` with count **0**) and an explicit **empty list** message (spec: e.g. “No comments yet”—match whatever string the view uses).
- **S2:** Same route for an image with **two or more** comments → **200**; response lists **all** comments **oldest first**; each row exposes **author username**, visible **body text** matching stored `content`, and a **timestamp**; summary **Comments:** count equals the number of listed rows.
- **S3:** Same URL **without** session cookie → **200**; comment **list** (and counts) match an authenticated `GET` for the same `id` (public read).

**Failure**

- **F1:** Missing or empty `id`, invalid numeric `id`, unknown `id`, or unsafe/missing file → **404** (unchanged detail behavior); response body must **not** include comment text from **other** images (e.g. grep for a comment string tied to another `image_id` must fail).

**Edge**

- **E1:** A comment whose stored `content` contains HTML metacharacters (e.g. `<` and `"`) appears **escaped** in the HTML source (e.g. `&lt;` / entities), not as raw markup.
- **E2:** Wrong HTTP method on the detail route (e.g. `POST /gallery/image?id=1`) → **404** or safe rejection per router, **no** mutation.

### Test Setup

No authentication required for reading comments. Set **`BASE`** to the running app root URL.

Use **`KNOWN_GOOD_ID`**: an `images.id` whose file exists under `public/` (auto-detect from `/gallery` like `docs/specs/gallery_image_details.md`, or `export KNOWN_GOOD_ID=<int>`).

For **S2** and ordering checks, seed **`database/camagru.db`** (or your configured DB) with two comments on `KNOWN_GOOD_ID` with distinct `content` and increasing `created_at` (or rely on insert order), using valid `user_id` values. For **S1**, pick an image id that still has **zero** comments (or delete test comments first).

**Optional — compare anonymous vs logged-in (S3):** use a cookie jar from register/login (see `docs/WORKFLOW-addendum-web-server.md`); `curl` the same detail URL with and without `-b cookies.txt` and compare the comments block (should be identical for this feature).

### Execute tests

```bash
BASE=http://localhost:8080
KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -s "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}

# S1 — valid detail, expect 200 (empty comments: adjust grep to your view’s empty-state string)
if [ -n "$KNOWN_GOOD_ID" ]; then
  code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
  echo "S1 detail HTTP -> $code (expect 200)"
  html=$(curl -s "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
  echo "$html" | grep -q 'Back to gallery' || echo "FAIL: expected detail page"
  echo "$html" | grep -q 'Comments:' || echo "FAIL: expected Comments summary"
  echo "$html" | grep -q 'No comments yet' || echo "NOTE: empty-state string may differ; set grep to match implementation"
fi

# S2 — after seeding 2+ comments on KNOWN_GOOD_ID: expect bodies to contain known substrings
# export COMMENT_A='first body' COMMENT_B='second body'
if [ -n "$KNOWN_GOOD_ID" ] && [ -n "${COMMENT_A:-}" ] && [ -n "${COMMENT_B:-}" ]; then
  html=$(curl -s "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
  echo "$html" | grep -qF "$COMMENT_A" || echo "FAIL: expected first comment body in HTML"
  echo "$html" | grep -qF "$COMMENT_B" || echo "FAIL: expected second comment body in HTML"
fi

# S3 — anonymous GET (no cookie)
if [ -n "$KNOWN_GOOD_ID" ]; then
  code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/gallery/image?id=$KNOWN_GOOD_ID")
  [ "$code" = "200" ] || echo "FAIL: S3 expected 200 without session"
fi

# F1 — 404 paths unchanged
curl -s -o /dev/null -w "F1 no id -> %{http_code}\n" "$BASE/gallery/image"
curl -s -o /dev/null -w "F1 bad id -> %{http_code}\n" "$BASE/gallery/image?id=0"
curl -s -o /dev/null -w "F1 unknown id -> %{http_code}\n" "$BASE/gallery/image?id=999999999"

# F1 — no cross-image leak: if OTHER_COMMENT is text that exists only on another image_id, it must not appear
# html=$(curl -s "$BASE/gallery/image?id=$KNOWN_GOOD_ID"); echo "$html" | grep -qF "$OTHER_COMMENT" && echo "FAIL: leaked other image comment"

# E1 — manual: insert a comment with literal < in content; view source must show escaped form

# E2 — wrong method
curl -s -o /dev/null -w "E2 POST -> %{http_code}\n" -X POST "$BASE/gallery/image?id=1"
```

**Manual / DB:** **E1** and **F1** leak check require tailored `sqlite3` data. **S5** (DB failure while loading comments): match `GalleryController::showImage()` / `detailLoadError` behavior—verify in a controlled environment.

---

## Spec-driven implementation gate (WORKFLOW §7.5)

Checkpoint before **application code** changes: confirm scope, artifacts, and tests; **wait for explicit “go ahead”** before implementing.

### 1. Files to read

| Path | Reason |
|------|--------|
| `docs/specs/comments_listing_per_image.md` | This spec (goal, behavior, plan, tests). |
| `docs/specs/comments_persistence.md` | `CommentRepository` contract and table shape. |
| `docs/specs/gallery_image_details.md` | Detail route, 404 rules, existing curl style. |
| `src/controller/GalleryController.php` | `showImage()` flow and variables passed to the view. |
| `src/repository/CommentRepository.php` | Extend with read method; match insert/DB style. |
| `src/repository/ImageRepository.php` | How `findPublicDetailById` supplies `comment_count` (avoid redundant queries if possible). |
| `src/views/gallery_image.php` | Where to render the list and empty state. |
| `src/routes/index.php` | Confirm no new route needed (`GET /gallery/image` only). |
| `docs/WORKFLOW-addendum-web-server.md` | HTTP/curl conventions. |

### 2. Files to modify

| Path | Reason |
|------|--------|
| `src/repository/CommentRepository.php` | Add `findByImageId` (or equivalent) with JOIN, ordering, prepared statement. |
| `src/controller/GalleryController.php` | After successful detail row + `imageSrc`, load comments array and pass to view (handle errors per S5 / existing `detailLoadError` pattern). |
| `src/views/gallery_image.php` | Comments subsection: loop, escape fields, empty state string consistent with **S1** grep in Tests. |

### 3. Files to create

None expected.

### 4. Feature spec

**Sufficient** for implementation: goal, behavior, constraints, and success criteria are clear; boundaries vs #27/#28/#31 are stated. **Gap to resolve during implementation:** pick one **exact** empty-state string and use it in both view and test grep (or document the chosen copy in the spec when implementing).

### 5. Implementation plan

**Sufficient:** steps are ordered (repository → controller → view → consistency → HTTP checks), small, and map to MVC. Optional refinement: if step 4 deduplicates count vs list, note whether `comment_count` stays from `ImageRepository` or is derived from `count($comments)` to avoid mismatch.

### 6. Test plan

**Sufficient** after §7.4: success / failure / edge covered; runnable `curl` block with `BASE` and `KNOWN_GOOD_ID`; seeding instructions for multi-comment **S2**; pointers for manual **E1**, **F1** leak, **S5**. **Optional gap:** automated assertion of **oldest-first** order may need two known bodies and `grep` position or manual check—acceptable for this spec.

### 7. Scope creep

Avoid: comment forms, POST endpoints, pagination, AJAX, moderation (#31), changing like behavior, new routes, `data-testid` proliferation unless needed. Resist redefining “comments summary” vs list heading in ways that confuse counts.

### 8. Architecture (ARCHITECT.md + MVC)

Matches project MVC: repository = SQL only, controller = orchestration, view = HTML + escaping. Aligns with subject-first discipline: one subfeature, small iteration. No new dependencies; prepared statements and `htmlspecialchars` preserve security rules.

**Gate outcome:** Ready for implementation after maintainer confirmation.
