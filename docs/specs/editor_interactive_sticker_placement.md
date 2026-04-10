# Feature: Editor interactive sticker placement

Goal
Let a logged-in user adjust the selected sticker on the editor preview before composition: **move** (position), **scale** (relative size within the canvas), and **rotate** (angle). The client sends validated numeric parameters with the existing `POST /editor/compose` flow; the server remains authoritative and produces the composed temporary image as today (then redirect to `/editor`).

Behavior
- On `/editor`, when a valid session base image exists, choosing a sticker opens an **adjustment step** on the preview area: the user can change position, scale, and rotation while seeing feedback (overlay aligned with the base image, e.g. via vanilla JS and CSS transforms and/or a small canvas—exact mechanism is implementation detail).
- **Compose** submits one sticker per request with **position**, **scale**, and **rotation** in addition to the sticker identifier. The server applies the same security rules as today: authenticated session, base file from `$_SESSION['editor_temp_image']` only, sticker resolved only through `StickerService` (no raw path trust).
- **Scale** is applied after the existing “fit sticker inside base” rule (see `ImageComposeService`): the user controls a **relative** size within the allowed range (e.g. a factor between a defined minimum and `1.0` meaning “as large as still fits the base at current rotation”), so the final drawn sticker never exceeds the base bounds. Exact parameterization (e.g. `scale` float 0–1, or percent) is fixed in the implementation plan; behavior is “user can shrink from max-fitting size, not enlarge beyond what fits.”
- **Rotation** is specified in degrees (range and winding fixed in the implementation plan, e.g. −180…180). The server composites so that the **entire sticker** (after scale and rotation) remains **fully inside** the base image, consistent with today’s “no overflow” policy; if a combination would overflow, inputs are **clamped** or adjusted in a documented way (prefer clamping position/scale rather than silent crop).
- Sticker **PNG alpha** must remain correct in the final output (same expectation as `editor_compose` / `ImageComposeService`).
- **Save**, **reset**, **capture**, **upload**, and **temp file cleanup** behaviors stay compatible with current editor flows (composed result still becomes the new session temp PNG in `public/tmp/`).
- **Out of scope:** multiple stickers in one compose; persisting placement in the database (see separate backlog: composition metadata).

Constraints
- **Stack:** PHP standard library only; **GD** for raster operations. No new frontend frameworks; vanilla JS/CSS only.
- **Security:** Validate and coerce all compose parameters server-side (`is_numeric` / ranges / types); reject missing or invalid sticker names as today; never use client strings as filesystem paths; escape all dynamic output in views with `htmlspecialchars`.
- **Architecture:** HTTP and validation in the controller; composition logic in the service layer; views output HTML only.
- **Regression:** Existing compose behavior for “default” placement (e.g. fixed defaults equivalent to current `x`/`y` hidden fields) must remain achievable—either as explicit defaults in the UI or as server-side defaults when optional fields are omitted (choose one approach in the implementation plan and document it).

Success Criteria
- An authenticated user with a session base image can adjust a sticker on the preview and submit compose; the returned temp preview shows the sticker at the chosen position, scale, and rotation (within clamp rules), with transparency preserved.
- Invalid or out-of-range parameters produce a safe error path (editor re-rendered with a clear message, no 500, no path leakage), same class of failure handling as current `compose`.
- Unauthenticated `POST /editor/compose` is rejected as today (e.g. redirect to login).
- Manual checks: at least two different rotations and a visibly smaller scale than default both produce distinct outputs; a second compose after upload/capture still works end-to-end.

## Implementation Plan

1 validate optional `scale` (e.g. 0.05–1.0, default `1`) and `angle` (e.g. −180…180, default `0`) in `EditorController::compose` alongside existing `sticker` / `x` / `y`; reject out-of-range values with the same error path as today
2 extend `ImageComposeService::compose` to accept scale and angle; after existing auto-fit resample, resample again by the user scale factor (minimum 1×1 px)
3 rotate the scaled sticker with GD preserving alpha; compute its width/height for placement and clamp `x`/`y` so the rotated sticker lies fully inside the base
4 copy the processed sticker onto the base and save the PNG; keep session temp swap and prior-tmp unlink behavior unchanged
5 replace per-sticker immediate POST forms in `editor.php` with one compose form: hidden `sticker`, `x`, `y`, `scale`, `angle`, and sticker thumbnails that only set selection + show the adjustment UI
6 add `public/js/editor_sticker_placement.js` to align an overlay with the base preview, pointer-drag for position, range inputs for scale and rotation, and sync all values into the hidden fields before submit
7 load the new script from `editor.php` (only when a base preview exists) and confirm `POST /editor/compose` via `curl` with `scale` and `angle` plus manual browser checks

## Tests

**Test cases**

- **Success:** Authenticated session with temp base image POSTs `/editor/compose` with valid `sticker`, numeric `x`/`y`, `scale` in range (e.g. `0.05`–`1`), `angle` in range (e.g. −180…180) → redirect back to `/editor` (or equivalent success); composed temp image updates session.
- **Success (regression):** Same as today: valid `sticker`, `x`, `y` with **`scale` and `angle` omitted** → defaults apply (`scale=1`, `angle=0`); composition succeeds.
- **Success:** Second POST with different `angle` and/or smaller `scale` after the first compose still succeeds (session still has a base temp file).
- **Failure:** POST without session → `302` to `/login`.
- **Failure:** `scale` outside allowed range or non-numeric → `200` editor page with composition error (no `500`).
- **Failure:** `angle` outside allowed range or non-numeric → `200` editor page with composition error.
- **Edge:** `x`/`y` beyond drawable bounds → position clamped (or defined safe behavior); still `200`/redirect without server error.
- **Edge:** Manual only — overlay drag/sliders match server output for the same numeric fields (pixel mapping on scaled `<img>` preview).

**Test Setup (authentication)**

```bash
rm -f cookies.txt
BASE=http://localhost:8080

curl -s -c cookies.txt -X POST "$BASE/register" \
  -d "email=stickerplace@test.com&username=stickerplace&password=Secret123!&confirm_password=Secret123!"

curl -s -c cookies.txt -b cookies.txt -X POST "$BASE/login" \
  -d "username=stickerplace&password=Secret123!"

curl -s -c cookies.txt -b cookies.txt -X POST "$BASE/editor/upload" \
  -F "base_image=@public/stickers/glasses.png"
```

**Execute tests**

```bash
BASE=http://localhost:8080

# Success: compose with scale and angle (run after Test Setup)
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=30&y=40&scale=0.5&angle=45"
# Expect: 302 (or 200 if implementation differs; no 500)

# Success: omit scale and angle — defaults
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=10&y=10"
# Expect: 302; same as legacy compose

# Success: second compose with different geometry
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=0&y=0&scale=0.2&angle=-90"
# Expect: 302

# Failure: no session
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=0&y=0&scale=1&angle=0"
# Expect: 302 to /login

# Failure: scale out of range (adjust boundary to match implementation, e.g. >1 or <min)
curl -s -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=10&y=10&scale=99&angle=0"
# Expect: 200 HTML with composition error

# Failure: angle out of range
curl -s -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=10&y=10&scale=1&angle=400"
# Expect: 200 HTML with composition error

# Failure: non-numeric scale
curl -s -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=10&y=10&scale=bad&angle=0"
# Expect: 200 HTML with composition error

# Edge: large x,y (clamping)
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=99999&y=99999&scale=1&angle=0"
# Expect: 302 or 200 without 500; sticker position clamped if implemented
```
