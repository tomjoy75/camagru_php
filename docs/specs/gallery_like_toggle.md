# Feature: Gallery like / unlike (toggle)

## Goal

Let **authenticated** users **add or remove** their like for a **published gallery image** in one controlled step, using the existing `likes` table (`user_id`, `image_id`, `UNIQUE(user_id, image_id)`). This implements [GitHub #32](https://github.com/tomjoy75/camagru_php/issues/32) and `docs/feature_tree.md` (only logged-in users; toggling per user per image). It builds on the public image detail flow from `docs/specs/gallery_image_details.md`; the page already shows aggregate **like count**—after a successful toggle, that count must stay **consistent** with the database (see also [GitHub #33](https://github.com/tomjoy75/camagru_php/issues/33) if counts need to appear elsewhere).

## Behavior

- **Who may act:** Only users with a valid session (`user_id` in session). Guests must **not** mutate likes; attempts are rejected the same way as other auth-gated mutations (e.g. redirect to `/login` without changing `likes`).
- **What toggles:** A **POST** request targets a **single** gallery `image_id` (positive integer identifying a row in `images`). If the pair `(current user, image_id)` **has no** like row, the handler **inserts** one. If a row **already exists**, the handler **deletes** that row. Repeating the same action is safe (idempotent outcome: second toggle undoes the first).
- **Validation:** Reject missing, non-numeric, zero, or negative `image_id` without applying any change. If `image_id` is syntactically valid but **no** row exists in `images`, do not insert into `likes`; respond with a safe error path (e.g. redirect with a short flash message or **404**, consistent with how the app treats unknown image ids elsewhere—pick one approach and document it in implementation).
- **After success:** Redirect so the user returns to the **same image detail** view (`GET /gallery/image?id=…`) with **302** (or project-standard redirect), so refreshed **Likes:** count matches `COUNT(*)` for that `image_id`. Optional session flash for success is allowed; avoid leaking internal errors.
- **Wrong HTTP method:** `GET` (or other methods) on the toggle route are **not** handled by the toggle action (e.g. **404** via the router), so likes cannot be changed by a link prefetch or accidental GET.
- **UI (minimal):** On the image detail page, when the user is logged in, show a form **POST**ing to the toggle route (hidden or visible `image_id`). For clarity, the server may load whether the current user already liked this image on **GET** detail and pass a boolean to the view so the control can read **Like** vs **Unlike** (still a full page round-trip—no requirement for AJAX; rich “live” behavior stays [GitHub #34](https://github.com/tomjoy75/camagru_php/issues/34)).
- **Out of scope:** Liking from the gallery list only (unless trivially the same POST is reused with a redirect back to list), comment flows, notifications, CSRF tokens (none in the project today), and any JavaScript-only or optimistic UI updates.

## Constraints

- **MVC:** Router → controller handles HTTP/session and redirect; **repository (or thin service)** owns `INSERT`/`DELETE`/`SELECT` on `likes`; **views** output HTML only and escape output with `htmlspecialchars` where dynamic.
- **PHP:** Standard library only; **prepared statements** for all SQL; no new dependencies.
- **Security:** Never trust raw POST ids—validate type/range before queries. Users may only create or delete **their own** like rows (bind `user_id` from session, not from the client). Do not expose stack traces or SQL to the client.
- **Data:** Rely on `database/schema.sql`: `likes` with `FOREIGN KEY` to `images` and `users`; uniqueness prevents duplicate likes per user per image.
- **Concurrency:** Normal SQLite semantics; duplicate rapid POSTs should not corrupt data thanks to `UNIQUE(user_id, image_id)`; handle constraint violations safely if they occur.

## Success Criteria

- **S1:** Authenticated user, valid existing `image_id`, currently **not** liked → **POST** toggle → **302** to detail `GET`, new row in `likes`, **Likes:** count increases by **1** vs before.
- **S2:** Same user, same image, currently **liked** → **POST** toggle again → row removed, **Likes:** count decreases by **1**, no duplicate rows possible.
- **S3:** Unauthenticated **POST** → no row added or removed; user sent to login (or equivalent), **likes** unchanged.
- **S4:** Invalid `image_id` (empty, non-numeric, ≤ 0) or unknown image → no like row created; safe response with no **500**.
- **S5:** **GET** on the toggle URL → **404** (or router-consistent safe rejection), no mutation.
- **S6:** Guest **GET** `/gallery/image?id=…` unchanged: page stays public; no like controls required for guests (or show disabled text only—implementation choice, but guests must not POST successfully without auth).

## Implementation Plan

1. Register `POST` route → `GalleryController::toggleLike` in `src/routes/index.php`.
2. Add `LikeRepository` with `hasLiked`, `insert`, and `deleteByUserAndImage` (prepared statements only).
3. In `toggleLike`, reject missing session → `302` `/login`, no DB writes.
4. In `toggleLike`, read POST `image_id`, validate positive int; on failure redirect safely (e.g. `/gallery` + flash) without mutating `likes`.
5. In `toggleLike`, if no `images` row for that id, same safe redirect (or `404`); else delete existing like or insert new one, then `302` to `/gallery/image?id=<id>`.
6. In `showImage`, when `user_id` is in session, load `hasLiked` and pass it to the view.
7. In `gallery_image.php`, render a logged-in-only `<form method="post">` to the toggle URL with hidden `image_id` and Like/Unlike label from `hasLiked`.

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

**Wire-up assumed for curl (change `LIKE_POST` / `IMAGE_FIELD` if routing or field names differ):** `POST /gallery/like` with form field `image_id`.

### Test cases

**Success**

- **S1:** Authenticated `POST` with valid `image_id`, image not yet liked → **302** `Location` → `/gallery/image?id=<id>`; subsequent `GET` detail shows **Likes:** count **+1** vs before toggle.
- **S2:** Same session, second `POST` (already liked) → **302** back to same detail; **Likes:** count **−1** vs after S1 (back to baseline).

**Failure**

- **F1:** No session cookie → **302** to `/login` (or equivalent); `POST` does not succeed.
- **F2:** Authenticated `POST` with missing / empty / non-numeric / ≤0 `image_id` → safe redirect or error response, **no 500**, no new `likes` row for garbage ids.
- **F3:** Authenticated `POST` with syntactically valid id that has **no** `images` row → no like inserted; safe handling (**404** or redirect per implementation).
- **F4:** `GET` on the like URL (e.g. `/gallery/like`) → **404** (or router-consistent rejection), **no** mutation.

**Edge**

- **E1:** Two authenticated `POST`s in a row when starting **unliked** → first adds, second removes; third adds again; counts stay consistent (no duplicate like rows).
- **E2:** Guest `GET /gallery/image?id=<valid>` → **200**; body must **not** require a like form (or form must not work without session—F1 already covers `POST`).

### Test Setup (authentication)

```bash
rm -f cookies.txt
BASE=http://localhost:8080
LIKE_POST="$BASE/gallery/like"
IMAGE_FIELD=image_id

# Unique user for this run (avoid duplicate email errors)
STAMP=$(date +%s)
EMAIL="like_test_${STAMP}@example.com"
USER="liker${STAMP}"
PASS='Testpass1!'

# KNOWN_GOOD_ID: existing gallery image (file on disk). Auto-detect from listing or set manually.
KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -s "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}

# Register then login; session in cookies.txt
curl -s -o /dev/null -c cookies.txt -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"
curl -s -o /dev/null -c cookies.txt -b cookies.txt -L -X POST "$BASE/login" \
  -d "email=$EMAIL" -d "password=$PASS"
```

### Execute tests

```bash
BASE=http://localhost:8080
LIKE_POST="$BASE/gallery/like"
IMAGE_FIELD=image_id
KNOWN_GOOD_ID=${KNOWN_GOOD_ID:-$(curl -s "$BASE/gallery" | grep -oE 'gallery/image\?id=[0-9]+' | head -1 | cut -d= -f2)}

like_count() {
  curl -s -b cookies.txt "$BASE/gallery/image?id=$1" | tr '\n' ' ' | sed -n 's/.*Likes:<\/dt>[[:space:]]*<dd[^>]*>\([0-9][0-9]*\).*/\1/p' | head -1
}

if [ -z "$KNOWN_GOOD_ID" ]; then
  echo "SKIP: set KNOWN_GOOD_ID or publish an image so /gallery lists gallery/image?id="
  exit 0
fi

# F4 — GET on toggle route must not mutate (expect 404 with current router)
curl -s -o /dev/null -w "F4 GET like URL -> %{http_code}\n" "$LIKE_POST"

# F1 — unauthenticated POST -> expect redirect to login
curl -s -o /dev/null -D /tmp/like_hdr.txt -w "F1 unauth POST HTTP %{http_code}\n" -X POST "$LIKE_POST" -d "${IMAGE_FIELD}=$KNOWN_GOOD_ID"
grep -i '^location:' /tmp/like_hdr.txt || true

# S1/S2/E1 — need cookies.txt from Test Setup
if [ ! -s cookies.txt ]; then
  echo "SKIP S1/S2/E1/F2/F3: run Test Setup first to create cookies.txt"
else
  c0=$(like_count "$KNOWN_GOOD_ID")
  curl -s -o /dev/null -D /tmp/t1.txt -b cookies.txt -X POST "$LIKE_POST" -d "${IMAGE_FIELD}=$KNOWN_GOOD_ID"
  echo "S1 first POST -> $(grep -i '^HTTP' /tmp/t1.txt | head -1)$(grep -i '^location:' /tmp/t1.txt | head -1)"
  c1=$(like_count "$KNOWN_GOOD_ID")
  curl -s -o /dev/null -D /tmp/t2.txt -b cookies.txt -X POST "$LIKE_POST" -d "${IMAGE_FIELD}=$KNOWN_GOOD_ID"
  echo "S2 second POST -> $(grep -i '^HTTP' /tmp/t2.txt | head -1)$(grep -i '^location:' /tmp/t2.txt | head -1)"
  c2=$(like_count "$KNOWN_GOOD_ID")
  echo "Like counts before/after toggle/unlike: $c0 -> $c1 -> $c2 (expect middle differs by +1 from start, end matches start if clean toggle)"

  # F2 — bad ids (expect no 500; adjust expectations if you return 302 to /gallery)
  for bad in '' '0' '-1' 'abc'; do
    curl -s -o /dev/null -w "F2 image_id='${bad:-empty}' -> %{http_code}\n" -b cookies.txt -X POST "$LIKE_POST" -d "${IMAGE_FIELD}=$bad"
  done

  # F3 — unknown image id
  curl -s -o /dev/null -w "F3 unknown id -> %{http_code}\n" -b cookies.txt -X POST "$LIKE_POST" -d "${IMAGE_FIELD}=999999999"
fi

# E2 — guest sees public detail (200)
curl -s -o /dev/null -w "E2 guest GET detail -> %{http_code}\n" "$BASE/gallery/image?id=$KNOWN_GOOD_ID"
```

**Manual / DB:** Confirm `likes` rows for `(user_id, image_id)` match toggles if HTML parsing fails. **E3 (DB failure):** same spirit as gallery detail error handling—no stack trace to client.
