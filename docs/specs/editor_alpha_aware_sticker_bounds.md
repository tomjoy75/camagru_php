# Feature: `Editor alpha-aware sticker placement bounds (server compose)`

Goal  
Make sticker placement validation in server compose reflect the visible sticker content (opaque pixels) instead of the full PNG rectangle, so stickers with transparent margins can be positioned naturally without weakening current safety rules.

Behavior  
During `POST /editor/compose`, placement bounds are computed from an alpha-aware "useful bounds" box derived from the selected sticker image after current scale/rotation inputs are applied through the existing compose flow.  
If the useful bounds stay inside the base image, compose succeeds as usual; if useful bounds exceed allowed limits, compose is rejected as today with existing safe error behavior.  
No routing, editor state machine, or UI workflow changes are introduced in this slice.

Constraints  
Keep scope server-side only in one pass: no preview/UI bound changes in this issue.  
Preserve current MVC boundaries and existing compose contract (`sticker`, `x`, `y`, `scale`, `angle`) and validation pipeline.  
Do not relax security checks for sticker filenames, auth/session gating, temp-file lifecycle, or other existing compose guards.  
Avoid broad redesign (no new persistence model, no optional policy expansions); implement the smallest rule change needed for alpha-aware bounds.

Success Criteria  
Composing a sticker with large transparent margins allows placements that were previously blocked only because of transparent padding, while still keeping visible sticker content within the base image.  
Out-of-bounds placements (based on visible content bounds) are still rejected safely and consistently.  
Existing compose behavior for normal stickers (without significant transparent padding) remains stable.  
No regression in auth protection, route behavior, or error handling for invalid compose requests.

## Implementation Plan

1 add a small GD helper to scan sticker alpha and return the non-transparent bounding box in sticker-local coordinates
2 wire the compose service path to compute scaled/rotated effective bounds from that alpha box instead of full sticker dimensions for bounds checks
3 keep existing fallback behavior when no useful alpha area is detected by treating the full sticker rectangle as the bounds source
4 keep compose rejection/success flow unchanged while swapping only the internal bounds predicate used before merge
5 add focused request-level checks for one transparent-margin sticker case that now passes and one visible-content out-of-bounds case that still fails

## Tests

**Test cases**

- **Success:** authenticated compose with a transparent-margin sticker near an edge succeeds when visible content remains inside the base.
- **Success:** authenticated compose with a normal sticker still succeeds for a clearly in-bounds placement.
- **Failure:** compose with placement that pushes visible content out of bounds is rejected safely.
- **Failure:** unauthenticated compose is rejected by auth guard (`/login` redirect).
- **Edge:** unknown sticker filename is rejected safely.
- **Edge:** transparent-area scan fallback path (no useful alpha bbox) does not crash and keeps safe behavior.

**Test Setup (authentication)**

```bash
BASE=http://localhost:8080
DATABASE_PATH=database/camagru.db
EMAIL="alpha.bounds.$(date +%s)@example.com"
USERNAME="alphabounds$(date +%s)"
PASSWORD='Aa1!testpass'

rm -f cookies.txt

# Start app first in another shell, e.g.:
# php -S 127.0.0.1:8080 public/index.php

# Register
curl -s -i -c cookies.txt -X POST "$BASE/register" \
  -d "email=$EMAIL&username=$USERNAME&password=$PASSWORD&confirm_password=$PASSWORD" > /tmp/alpha_reg.out

# Confirm email (required by current login policy)
TOKEN="$(sqlite3 "$DATABASE_PATH" "SELECT confirmation_token FROM users WHERE email='$EMAIL' ORDER BY id DESC LIMIT 1;")"
curl -s -i "$BASE/register/confirm?token=$TOKEN" > /tmp/alpha_confirm.out

# Login
curl -s -i -c cookies.txt -b cookies.txt -X POST "$BASE/login" \
  -d "username=$USERNAME&password=$PASSWORD" > /tmp/alpha_login.out

# Create a small base image fixture for upload
php -r '$im=imagecreatetruecolor(320,240);$bg=imagecolorallocate($im,240,240,240);imagefill($im,0,0,$bg);imagepng($im,"/tmp/alpha_base.png");imagedestroy($im);'

# Create workspace base with a valid sticker
curl -s -i -b cookies.txt -F "image=@/tmp/alpha_base.png;type=image/png" -F "sticker=hat.png" \
  "$BASE/editor/upload" > /tmp/alpha_upload.out
```

**Execute tests**

```bash
BASE=http://localhost:8080

# S1: transparent-margin sticker near edge should now be accepted (expect redirect, not in-page error)
curl -s -i -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=heart_glasses.png&x=5&y=5&scale=1&angle=0" > /tmp/alpha_s1.out

# S2: normal sticker in-bounds still accepted
curl -s -i -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=hat.png&x=40&y=40&scale=0.5&angle=0" > /tmp/alpha_s2.out

# F1: clearly out-of-bounds visible content should be rejected
curl -s -i -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=hat.png&x=9999&y=9999&scale=1&angle=0" > /tmp/alpha_f1.out

# F2: guest compose should redirect to login
curl -s -i -X POST "$BASE/editor/compose" \
  -d "sticker=hat.png&x=10&y=10&scale=1&angle=0" > /tmp/alpha_f2.out

# E1: invalid sticker filename should be rejected safely
curl -s -i -b cookies.txt -X POST "$BASE/editor/compose" \
  -d "sticker=../../etc/passwd&x=10&y=10&scale=1&angle=0" > /tmp/alpha_e1.out

# Quick status checks
echo "S1 status: $(rg -n '^HTTP/' /tmp/alpha_s1.out | head -n 1)"
echo "S2 status: $(rg -n '^HTTP/' /tmp/alpha_s2.out | head -n 1)"
echo "F1 status: $(rg -n '^HTTP/' /tmp/alpha_f1.out | head -n 1)"
echo "F2 status: $(rg -n '^HTTP/' /tmp/alpha_f2.out | head -n 1)"
echo "E1 status: $(rg -n '^HTTP/' /tmp/alpha_e1.out | head -n 1)"
```
