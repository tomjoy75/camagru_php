# Feature: Editor — scale sticker to fit base image when larger than base

Goal
When composing a base image with a sticker, if the sticker’s pixel width or height exceeds the base image, scale the sticker down uniformly so it fits entirely within the base canvas, then composite it at the requested (clamped) position. Users should no longer see a hard failure solely because a sticker asset is larger than their photo.

Behavior

- After loading the base image and sticker (same sources and validation as today’s compose flow), compare sticker dimensions to base dimensions.
- If both sticker width and height are less than or equal to the base width and height, behavior stays unchanged: no scaling, position `(x, y)` is clamped so the sticker remains fully inside the base.
- If either sticker dimension exceeds the corresponding base dimension, compute a single scale factor so that the scaled sticker’s width and height are at most the base width and height while preserving aspect ratio (uniform scale). Typical approach: `scale = min(baseWidth / stickerWidth, baseHeight / stickerHeight)` capped at `1.0` when no downscale is needed.
- Apply that scaling to produce an in-memory sticker image (or equivalent) at the reduced size, then composite that scaled sticker onto the base at `(x, y)` with the same “fully inside” clamping rules as today, using the scaled dimensions for `maxX` / `maxY`.
- Transparency: scaled sticker must still preserve PNG alpha the same way as the current compose path.
- The HTTP API (`POST /editor/compose` with sticker id and `x`, `y`) does not need new parameters unless product requirements change; scaling is automatic on the server when dimensions require it.

Constraints

- PHP standard library only; use GD for resize and copy (e.g. `imagescale` or `imagecopyresampled` on a true-color image with alpha handling as required).
- Sticker filename resolution and session-bound base image rules remain unchanged; no trust of arbitrary paths.
- Scaling is server-side only during composition; sticker files on disk are not modified.
- Avoid quality regressions for normal-sized stickers (no unnecessary resample when already fitting).

Success Criteria

- Composing with a sticker whose native width or height is greater than the base image succeeds and produces a temporary composed PNG where the sticker appears scaled down and fully on-canvas, with alpha preserved.
- Composing with a sticker that already fits the base behaves identically to the pre-feature behavior (pixel-accurate placement and clamping).
- Automated or manual checks: use a small base image and a large sticker asset → composition succeeds; use equal or larger base → output matches previous expectations for the same `x`, `y`.

## Implementation Plan

1. add private helper: uniform scale factor and target width/height from base and native sticker dimensions (cap scale at 1)
2. add private helper: given sticker GdImage and target size, return drawable GdImage with alpha preserved (no copy when scale is 1)
3. in `ImageComposeService::compose()`, drop the oversized-sticker error branch; obtain drawable sticker from step 2; use its dimensions for clamping and `imagecopy`
4. destroy drawable sticker resource when it is not the original loaded sticker; keep save/session flow unchanged
5. verify `POST /editor/compose`: oversized sticker on small base succeeds; sticker that already fits matches pre-change placement/output

## Tests

**Test cases**

- **Success:** Authenticated user uploads a base image smaller than a chosen sticker (native PNG dimensions); `POST /editor/compose` with valid `sticker`, `x`, `y` → `302` to `/editor` and session temp image updates (no “Sticker is larger than base image” / generic compose failure for that case).
- **Success:** Base image dimensions are greater than or equal to the sticker on both axes; compose with the same `x`, `y` as before the feature → same HTTP outcome and equivalent pixel placement as pre-change (no unnecessary resample path).
- **Failure:** Unauthenticated `POST /editor/compose` → `302` to `/login`. No base in session, invalid sticker id, non-numeric `x`/`y` → `200` editor HTML with compose error (unchanged from existing compose behavior).
- **Edge:** `x` or `y` negative or larger than drawable bounds → sticker position clamped as today (fully on canvas). Sticker with transparency → visible pixels and alpha look correct on composed PNG (manual check via `/editor` or `/tmp/…`).

**Test Setup (authentication)**

```bash
rm -f cookies.txt
BASE=http://localhost:8080

# Register
curl -s -c cookies.txt -X POST "$BASE/register" \
  -d "email=scalefit@test.com&username=scalefituser&password=Secret123!&confirm_password=Secret123!"

# Login
curl -s -c cookies.txt -b cookies.txt -X POST "$BASE/login" \
  -d "username=scalefituser&password=Secret123!"

# Tiny base (40×40) so typical stickers are larger — requires PHP CLI with GD
php -r '$im = imagecreatetruecolor(40, 40); $g = imagecolorallocate($im, 220, 220, 220); imagefill($im, 0, 0, $g); imagepng($im, "tiny_base.png"); imagedestroy($im);'

curl -s -c cookies.txt -b cookies.txt -X POST "$BASE/editor/upload" \
  -F "base_image=@tiny_base.png"
```

**Execute tests**

```bash
BASE=http://localhost:8080

# Success: sticker downscaled to fit small base (run after Test Setup)
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=0&y=0"
# Expect: 302

# Edge: large coordinates — should still succeed (clamped), not 500
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=9999&y=9999"
# Expect: 302

# Failure: no session
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=0&y=0"
# Expect: 302 to /login

# Failure: invalid sticker (run after Test Setup so base exists)
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=nope.png&x=0&y=0"
# Expect: 200 (editor with compose error)

# Regression (larger base, sticker fits): new session
rm -f cookies.txt
curl -s -c cookies.txt -X POST "$BASE/register" \
  -d "email=scalefit2@test.com&username=scalefituser2&password=Secret123!&confirm_password=Secret123!"
curl -s -c cookies.txt -b cookies.txt -X POST "$BASE/login" \
  -d "username=scalefituser2&password=Secret123!"
curl -s -c cookies.txt -b cookies.txt -X POST "$BASE/editor/upload" \
  -F "base_image=@public/stickers/glasses.png"
curl -s -o /dev/null -w "%{http_code}\n" -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=glasses.png&x=10&y=10"
# Expect: 302 (self-compose overlay sanity; use another sticker filename if the UI lists multiple)
```
