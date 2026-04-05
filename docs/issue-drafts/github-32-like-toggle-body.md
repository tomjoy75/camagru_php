## Overview

Authenticated users can **toggle** their like on a **published gallery image** in one step: `POST` inserts a `likes` row if none exists for `(user_id, image_id)`, otherwise deletes it. Guests cannot mutate `likes`. After success, redirect to `GET /gallery/image?id=…` so the **Likes:** count stays correct. Full behavior, constraints, and success criteria: feature spec below.

**Status (2026-04-05):** Implemented on branch with `LikeRepository`, `GalleryController::toggleLike`, route `POST /gallery/like`, and like form on `gallery_image.php`. Spec curl tests run: F4/F1/S1–S2/E1/F2/F3/E2 passed (see comment or validation below).

## Spec reference (source of truth)

`docs/specs/gallery_like_toggle.md` — Goal, Behavior, Constraints, Success Criteria (S1–S6), **Implementation Plan**, and **Tests** (curl setup + execute blocks).

**HTTP contract for tests:** `POST /gallery/like` with form field `image_id`.

**Related:** `docs/specs/gallery_image_details.md` (detail page); follow-up [#33](https://github.com/tomjoy75/camagru_php/issues/33) (like count elsewhere); [#34](https://github.com/tomjoy75/camagru_php/issues/34) (live/AJAX like state — out of scope here).

## Implementation checklist

- [x] `POST /gallery/like` → `GalleryController::toggleLike` in `src/routes/index.php`
- [x] Add `LikeRepository`: `hasLiked`, `insert`, `deleteByUserAndImage` (prepared statements)
- [x] `toggleLike`: no session → `302` `/login`, no DB writes
- [x] `toggleLike`: validate POST `image_id` (positive int); on failure safe redirect to `/gallery`, no `likes` mutation
- [x] `toggleLike`: no `images` row → `302` `/gallery`; else toggle like → `302` `/gallery/image?id=<id>` (UNIQUE race → `302` back to detail)
- [x] `showImage`: if logged in, load `hasLiked`, pass to view
- [x] `gallery_image.php`: logged-in-only form to `/gallery/like`, hidden `image_id`, Like vs Unlike from `hasLiked`

## Test checklist

Run **Test Setup (authentication)** then **Execute tests** bash blocks from the spec (`## Tests`). Adjust `BASE` / `KNOWN_GOOD_ID` as needed.

- [x] **S1** — Auth POST, not yet liked → 302 to detail; **Likes:** +1 vs before
- [x] **S2** — Second POST → unlike; **Likes:** −1 vs after S1
- [x] **F1** — Unauthenticated POST → redirect login; no `likes` change
- [x] **F2** — Bad `image_id` (empty, 0, −1, non-numeric) → no 500, no bogus rows (302 `/gallery`)
- [x] **F3** — Unknown image id → no insert; safe response (302)
- [x] **F4** — `GET /gallery/like` → 404, no mutation
- [x] **E1** — Double toggle restores count (observed: counts 2 → 3 → 2 on sample image)
- [x] **E2** — Guest `GET` detail → 200; like control not actionable without session (F1 covers POST)
- [x] **Manual** — Optional: confirm `likes` rows in DB; constraint violations handled without stack trace to client

## Close criteria

Behavior matches spec success criteria; implementation + test checklists above satisfied; project board → **Done** when merged / you confirm.
