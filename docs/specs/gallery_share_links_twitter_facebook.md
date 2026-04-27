# Feature: `Gallery image share links (Twitter/Facebook)`

Goal  
Add a small, user-visible sharing capability on the public image detail page so anyone can share a gallery image using standard social intent URLs, without introducing new backend complexity.

Behavior  
On `GET /gallery/image?id=...`, render a compact share section with two links:
- Twitter/X intent link: `https://twitter.com/intent/tweet?url=<encoded_page_url>&text=<encoded_short_text>`
- Facebook sharer link: `https://www.facebook.com/sharer/sharer.php?u=<encoded_page_url>`

The shared URL points to the canonical public image-detail URL for the current image, built from app base URL + `/gallery/image?id=<id>`. Links are visible to guests and authenticated users, and open in a new tab.

Constraints  
- Keep scope to static share links only (no SDKs, no share counts, no Open Graph/Twitter card work in this slice).
- Preserve current MVC boundaries and existing routing; do not add new routes.
- Build URLs safely with proper encoding (`rawurlencode` / equivalent for query values).
- Use secure external-link attributes: `target="_blank"` with `rel="noopener noreferrer"`.
- Do not alter gallery image detail business behavior (likes/comments/view logic stays unchanged).

Success Criteria  
- On a valid image detail page, both Twitter/X and Facebook share links are present.
- Generated `href` values include correctly encoded query parameters and the expected image URL.
- Share links are accessible without login and open safely in a new tab.
- Existing image detail functionality continues to work unchanged.

## Implementation Plan

1 add share-url inputs to gallery image view context using existing image id and app base URL
2 build canonical image-detail URL string for current image and encode it for outbound query params
3 generate Twitter/X intent href with encoded `url` and encoded short `text` values
4 generate Facebook sharer href with encoded `u` value for the same canonical image URL
5 render a small share block in `gallery_image` view with both links and safe external-link attributes
6 verify `GET /gallery/image?id=<valid>` HTML contains both href patterns and required `target`/`rel` attributes

## Tests

**Test cases**

- Success: `GET /gallery/image?id=<valid>` returns `200` and includes one Twitter/X intent link and one Facebook sharer link.
- Success: both links include `target="_blank"` and `rel="noopener noreferrer"`.
- Failure: invalid image id (non-existent) does not render fake share links for a missing image page state.
- Failure: wrong route (e.g. `/gallery/images`) returns router-safe failure (`404`).
- Edge: generated share URL keeps the current image id in encoded form (`.../gallery/image?id=<id>` encoded inside provider query).
- Edge: guest access (no cookie/session) still shows share links on public image detail.

**Execute tests**

```bash
set -euo pipefail

BASE="http://localhost:8080"
IMAGE_ID="$(sqlite3 database/camagru.db "SELECT id FROM images ORDER BY id DESC LIMIT 1;")"

# Guard: need one existing image for detail-page checks
test -n "$IMAGE_ID"

# S1: public detail page returns 200
curl -s -o /tmp/share_detail.html -w "%{http_code}\n" "$BASE/gallery/image?id=$IMAGE_ID" | grep -q '^200$'

# S2: Twitter/X intent link exists
rg -q 'https://twitter\.com/intent/tweet\?url=' /tmp/share_detail.html

# S3: Facebook sharer link exists
rg -q 'https://www\.facebook\.com/sharer/sharer\.php\?u=' /tmp/share_detail.html

# S4: safe external-link attrs exist on share links
rg -q 'target="_blank"' /tmp/share_detail.html
rg -q 'rel="noopener noreferrer"' /tmp/share_detail.html

# E1: encoded canonical image-detail URL appears in share href payload
ENCODED_TARGET="$(php -r 'echo rawurlencode("http://localhost:8080/gallery/image?id=" . $argv[1]);' "$IMAGE_ID")"
rg -q "$ENCODED_TARGET" /tmp/share_detail.html

# F1: invalid image id should not return normal 200 detail page
curl -s -o /tmp/share_invalid.html -w "%{http_code}\n" "$BASE/gallery/image?id=999999999" | rg -q '^(404|302)$'

# F2: wrong route typo returns 404
curl -s -o /tmp/share_typo.html -w "%{http_code}\n" "$BASE/gallery/images?id=$IMAGE_ID" | grep -q '^404$'
```
