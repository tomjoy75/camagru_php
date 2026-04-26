# Feature: Likes — live like state (AJAX)

## Goal

Improve the gallery **image detail** experience so a logged-in user can **like or unlike** a published image **without a full page reload**, while the **Like / Unlike** control and the **aggregate like count** stay **exactly consistent** with the server after each action. This implements [GitHub #34](https://github.com/tomjoy75/camagru_php/issues/34) and extends `docs/specs/gallery_like_toggle.md` (POST toggle, `LikeRepository`, same data rules)—only the **delivery** changes (background request + DOM update).

## Behavior

- **Where it applies (MVP):** **`GET /gallery/image?id=…`** only. The gallery **grid** may keep showing counts as today (no like button required on the list in this slice); a follow-up may reuse the same JSON contract for tiles if desired.
- **Who sees it:** Same as today: **guests** see the page and counts but **no** like control; **logged-in** users see the control. For them, activating the control uses **`fetch` (or equivalent)** to send the same mutation as today’s form (`POST /gallery/like` with field **`image_id`**), then updates the UI from the **response** (see below)—**no** full document navigation on success.
- **Server truth (non-optimistic MVP):** After the server confirms success, the client updates:
  - the visible **aggregate like count** for that image to match the server, and  
  - the control label/state to **Like** vs **Unlike** according to whether the **current user** now has a row in `likes` for that image.  
  Do **not** rely on guessing the new count from the previous count until the server answers (that is **optimistic UI**, optional later).
- **Progressive enhancement:** If JavaScript is disabled or fails, behavior should remain acceptable: e.g. keep a **real** `<form method="post">` as fallback, or document that JS-only is acceptable for this optional issue—pick one in implementation and test it once.
- **Errors / auth:** If the server responds as **unauthenticated** (e.g. session expired), follow the same outcome as today (**redirect to `/login`** or equivalent); the client should **not** leave the UI showing a wrong “liked” state—either follow the redirect or reload and show login (implementation choice, must be consistent and tested).
- **Invalid image / DB edge cases:** Same rules as `gallery_like_toggle.md`: no silent corruption; failed toggles must **not** update the count or label as if success occurred.
- **Optional phase 2 (optimistic):** After MVP is stable, the same issue may add **optimistic** updates (flip UI immediately, then **revert** label + count if the request fails). Out of scope until MVP criteria pass.

## Constraints

- **MVC:** Keep **toggle logic** in `GalleryController` (or a thin helper it calls); keep **SQL** in `LikeRepository`. **Views** remain escaped HTML; new behavior may add **one small script** under `public/js/` and minimal **hooks** in `gallery_image.php` (e.g. `data-*` attributes, `id`s for the count and button)—no business rules in the script beyond “send POST, read response, patch DOM”.
- **Security:** Reuse existing rules: `user_id` from **session only**; validate **`image_id`** server-side; no new trust in client-supplied user identity. **CSRF:** The project still has **no CSRF tokens** (see `gallery_like_toggle.md`); this feature does not require introducing them in the MVP slice, but be aware cross-site POST risk is unchanged.
- **HTTP contract:** `GET` on `/gallery/like` must remain a **safe non-mutating** rejection (**404** or router-consistent). Define explicitly how a JSON success response is selected (examples: `Accept: application/json`, or a small query flag)—**one** documented contract for `fetch` and for manual `curl` tests.
- **PHP / stack:** Standard library only; **prepared statements** unchanged; no new Composer dependencies for this feature alone.
- **Accessibility:** After toggle, screen readers should get a sensible result (e.g. **live region** announcement or **focus** + updated **aria-pressed** / button text—minimal acceptable bar to be fixed in implementation).

## Success Criteria

- **SC1:** Logged-in user on **image detail** clicks **Like** (when not yet liked) → **no full page reload**; when the response arrives, the count shows **+1** vs before and the control reads **Unlike** (or equivalent).
- **SC2:** Same user clicks again → **Unlike** path removes like; count **−1** and control returns to **Like**; still no full reload.
- **SC3:** After several toggles, the displayed count and Like/Unlike state **match** a fresh `GET` of the same detail page (server is source of truth).
- **SC4:** Guest or logged-out **POST** behavior unchanged from `gallery_like_toggle.md` (no mutation without session); client must not show a false “liked” state after a failed auth response.
- **SC5:** Rapid double-clicks do not produce impossible UI (e.g. disable button while in flight, or queue requests—implementation choice); final UI still matches DB after responses settle.
- **SC6 (optional follow-up):** Gallery **list** tiles update counts without navigation—**not** required for MVP closure of #34 if this spec’s MVP is explicitly detail-only.

## Implementation Plan

**Reuse, don’t rebuild.** Likes are already implemented: `POST /gallery/like` → `GalleryController::toggleLike` → `LikeRepository` (`hasLiked` / `insert` / `deleteByUserAndImage`), with `hasLiked` + counts loaded in `showImage` and the form in `gallery_image.php` (see `docs/specs/gallery_like_toggle.md`). This feature adds only a **response shape** (JSON for `fetch`) and **client-side DOM updates**—no second toggle pipeline, no duplicate business rules.

**If you replace** the classic full-page submit with JS-only interaction, **remove** the obsolete path (e.g. duplicate handlers or unused markup) so the codebase stays one clear story—not a parallel “gas factory” of two systems doing the same job.

1. In `GalleryController::toggleLike`, after a successful toggle, if `Accept` requests `application/json`, respond `200` with `Content-Type: application/json` and a small JSON object (`liked`, `like_count`); keep existing `302` redirects for errors and for non-JSON success.
2. Verify the JSON path with an authenticated `curl` `POST` to `/gallery/like` (same `image_id` field) and a JSON `Accept` header; confirm body matches DB after like and after unlike.
3. In `gallery_image.php`, add minimal `id` / `data-*` hooks on the like count and the like control for JS targets; load a new script from `public/js/` only when the like form is shown.
4. Add `public/js/gallery_image_like.js`: on like control submit, `preventDefault`, `fetch` `POST` with `FormData` + `Accept: application/json`, parse JSON on `200`, update count node and button label (`Like` / `Unlike`).
5. Disable the like control while the request is in flight; re-enable after response handling completes.
6. On failed response, non-JSON body, or `302` to `/login`: do not update count or label; assign `window.location` from `Response.url` when redirected, otherwise optionally `location.reload()` once.

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

Conventions: `BASE` points at the running app; JSON success uses header **`Accept: application/json`** and response body fields **`liked`** (boolean) and **`like_count`** (non-negative integer) on **`200`**. Error paths keep existing **`302`** redirects from `gallery_like_toggle.md` unless the implementation explicitly documents JSON errors (not required here).

**Test cases**

- **Success**
  - **S1:** Authenticated `POST /gallery/like` with valid `image_id` and `Accept: application/json` → **`200`**, `Content-Type` JSON, body `liked` / `like_count` match DB after toggle.
  - **S2:** Second authenticated JSON `POST` for the same image → **`200`**, `liked` flipped, `like_count` changes by exactly **±1** vs previous JSON response.
  - **S3:** Authenticated `POST` **without** `Accept: application/json` → **`302`** to `/gallery/image?id=…` (legacy behavior unchanged).
- **Failure**
  - **F1:** No session cookie, same `POST` + `Accept: application/json` → **`302`** to `/login` (or equivalent); response body must **not** be treated as a successful JSON toggle.
  - **F2:** Session valid, missing / invalid `image_id` → **`302`** (or router-safe response), **no `200` JSON success** shape.
  - **F3:** Valid session, unknown `image_id` → safe redirect / **`404`** per existing toggle rules; **no** successful like row for garbage ids.
  - **F4:** `GET /gallery/like` → **`404`** (no mutation).
- **Edge**
  - **E1:** Three or more alternating JSON toggles in sequence → each **`200`** body self-consistent; after run, `like_count` on detail `GET` matches last JSON `like_count`.
  - **E2:** (Manual) Logged-in image detail: like/unlike via UI → **no full document reload**; count and button label match refreshed detail once.

**Test Setup (authentication)**

```bash
rm -f cookies.txt
BASE=http://localhost:8080
LIKE_POST="$BASE/gallery/like"
STAMP=$(date +%s)
EMAIL="like_json_${STAMP}@example.com"
USER="likejson${STAMP}"
PASS='Testpass1!'

curl -sS -o /dev/null -c cookies.txt -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"
curl -sS -o /dev/null -c cookies.txt -b cookies.txt -L -X POST "$BASE/login" \
  -d "username=$USER" -d "password=$PASS"

KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -sS "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}
```

**Execute tests**

```bash
BASE=http://localhost:8080
LIKE_POST="$BASE/gallery/like"
KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -sS "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}
JSON_ACCEPT='Accept: application/json'

# F4 — GET must not mutate
curl -sS -o /dev/null -w "F4 GET /gallery/like -> %{http_code}\n" "$LIKE_POST"

# F1 — JSON accept but no cookie -> expect redirect to login, not 200 JSON success
curl -sS -D /tmp/f1.txt -o /tmp/f1.body -w "F1 unauth POST http=%{http_code}\n" -X POST "$LIKE_POST" -H "$JSON_ACCEPT" -d "image_id=${KNOWN_GOOD_ID:-1}"
grep -i '^location:' /tmp/f1.txt || true

if [ -z "$KNOWN_GOOD_ID" ] || [ ! -s cookies.txt ]; then
  echo "SKIP S/F/E (json): set KNOWN_GOOD_ID and run Test Setup for cookies.txt"
  exit 0
fi

# S3 — legacy non-JSON success path still redirects
curl -sS -D /tmp/s3.txt -o /dev/null -w "S3 no Accept json http=%{http_code}\n" -b cookies.txt -X POST "$LIKE_POST" -d "image_id=$KNOWN_GOOD_ID"
grep -i '^location:' /tmp/s3.txt | head -1

# F2 — bad image_id with session (expect not 200 JSON success)
curl -sS -o /tmp/f2.body -w "F2 bad id http=%{http_code}\n" -b cookies.txt -X POST "$LIKE_POST" -H "$JSON_ACCEPT" -d "image_id=0"

# F3 — unknown image id
curl -sS -o /tmp/f3.body -w "F3 unknown id http=%{http_code}\n" -b cookies.txt -X POST "$LIKE_POST" -H "$JSON_ACCEPT" -d "image_id=999999999"

# S1 / S2 / E1 — JSON success: print body + http code (expect 200 + JSON)
json_toggle() {
  curl -sS -b cookies.txt -D /tmp/jh.txt -o /tmp/jb.txt -w "%{http_code}" \
    -X POST "$LIKE_POST" -H "$JSON_ACCEPT" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "image_id=$1"
}
code1=$(json_toggle "$KNOWN_GOOD_ID"); echo "S1 first JSON POST http=$code1 body=$(cat /tmp/jb.txt)"
code2=$(json_toggle "$KNOWN_GOOD_ID"); echo "S2 second JSON POST http=$code2 body=$(cat /tmp/jb.txt)"
code3=$(json_toggle "$KNOWN_GOOD_ID"); echo "E1 third JSON POST http=$code3 body=$(cat /tmp/jb.txt)"

# Parse last JSON body when implementation returns 200 + JSON (Python 3)
python3 -c "import json; d=json.load(open('/tmp/jb.txt')); print('parsed', d.get('liked'), d.get('like_count'))" 2>/dev/null || true
```

**Manual (browser):** open `/gallery/image?id=<KNOWN_GOOD_ID>` while logged in; use like control; confirm network shows `POST /gallery/like` with `Accept: application/json`, **`200`** + JSON, and UI updates without a full navigation (**E2**).

See `docs/WORKFLOW-addendum-web-server.md` (§ Test plan — HTTP with curl). Align field names (`liked`, `like_count`) with the implementation if they differ.
