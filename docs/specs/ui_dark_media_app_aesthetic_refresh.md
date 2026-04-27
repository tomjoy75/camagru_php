# Feature: `UI dark “media app” aesthetic refresh (Tailwind)`

Goal  
Refresh the visual look of Camagru (colors, contrast, typography, cards, hover/focus states) toward a clean **dark** UI in the spirit of modern media apps: dark background, light text, one strong accent, airy surfaces. **Branding, routes, and page structure stay unchanged**—this is presentation only.

Behavior  
- **Global shell:** `layout.php`, `header.php`, and `footer.php` use a coherent dark theme (page background, header/footer bars, readable links and controls).  
- **High-traffic views** (implemented in phased follow-ups on the same feature): `gallery.php`, `gallery_image.php`, `editor.php`, and authentication / account settings forms share the same visual language (inputs, primary/secondary buttons, alerts, cards).  
- **Responsive behavior** matches today: same Tailwind breakpoints and layout patterns (e.g. single column on small screens, existing multi-column grids). Changes are **token-style class swaps** (`bg-*`, `text-*`, `border-*`, `shadow-*`, `ring-*`) unless a change is purely cosmetic and verified not to alter layout on narrow and wide viewports.  
- **No new product chrome:** no new tabs, player bars, or top-level navigation sections—**aesthetic only**.

Constraints  
- **Tailwind + existing HTML** only for this pass; do not introduce new JS-heavy UI libraries.  
- Do **not** change routing, controllers, or business rules; views may only change **classes/markup** where needed for contrast (e.g. semantic wrappers), not request handling.  
- Do **not** regress responsive layout: no new horizontal scroll from styling; same column/grid **structure** as before.  
- **Accessibility:** body text and interactive elements meet readable contrast; focus states remain visible on keyboard navigation.  
- Do **not** copy any third-party brand pixel-perfect; stay generic “dark media app” feel.

Success Criteria  
- **Mobile / narrow:** Same column behavior as before; readable text; no horizontal scroll introduced by styling.  
- **Desktop:** Same grid/card structure as before; only look changes.  
- **A11y:** Sufficient contrast for body copy; focus rings visible on links, buttons, and form controls.  
- **Tech:** Still HTML + Tailwind; no new features or IA changes; gallery/editor **behavior** unchanged.

## Implementation Plan

1 update `layout.php` root wrappers for dark page background default text and focus ring tokens
2 update `header.php` bar links and buttons to match shell contrast and hover focus states
3 update `footer.php` bar and links to match shell contrast and hover focus states
4 swap Tailwind classes on login register password reset and profile settings views for inputs buttons alerts and cards
5 swap Tailwind classes on `gallery.php` for list tiles pagination and empty states without changing grid columns
6 swap Tailwind classes on `gallery_image.php` for detail card meta comments likes and share block surfaces
7 swap Tailwind classes on `editor.php` for panels toolbars messages and preview chrome without changing layout grids

## Tests

**Test cases**

- **Success:** `GET /` returns `302` with `Location` pointing at `/gallery`.  
- **Success:** `GET /gallery`, `GET /login`, `GET /register` return `200` and a full HTML document without PHP fatals in the body.  
- **Success:** `GET /gallery/image?id=<valid>` returns `200` when at least one image exists in the DB (same as today).  
- **Failure:** `GET` on an unknown path returns `404`.  
- **Failure:** `GET /gallery/image?id=<nonexistent>` returns `404` or `302` (unchanged router behavior; no `200` with a broken page).  
- **Edge:** `GET /editor` without a session cookie returns `302` to `/login` (auth gate unchanged by styling).  
- **Edge (manual):** narrow (~375px) and wide (~1280px) viewport spot-check after each implementation step—no new horizontal scroll; keyboard focus visible on header links and primary controls (not asserted by curl).

**Execute tests**

```bash
set -euo pipefail

BASE="http://localhost:8080"

# S1: root redirects to gallery
curl -sS -D /tmp/ui_root.hdr -o /dev/null "$BASE/"
grep -qE '^HTTP/[0-9.]+ 302' /tmp/ui_root.hdr
grep -qi '^Location: .*/gallery' /tmp/ui_root.hdr

# S2: public gallery page 200, no obvious PHP error output in HTML
curl -sS -o /tmp/ui_gallery.html -w "%{http_code}\n" "$BASE/gallery" | grep -q '^200$'
! rg -q 'Fatal error|Parse error' /tmp/ui_gallery.html

# S3: login form 200
curl -sS -o /tmp/ui_login.html -w "%{http_code}\n" "$BASE/login" | grep -q '^200$'
! rg -q 'Fatal error|Parse error' /tmp/ui_login.html

# S4: register form 200
curl -sS -o /tmp/ui_register.html -w "%{http_code}\n" "$BASE/register" | grep -q '^200$'
! rg -q 'Fatal error|Parse error' /tmp/ui_register.html

# S5: gallery image detail when at least one image exists (skip if DB empty)
IMAGE_ID="$(sqlite3 database/camagru.db "SELECT id FROM images ORDER BY id DESC LIMIT 1;" 2>/dev/null || true)"
if test -n "${IMAGE_ID:-}"; then
  curl -sS -o /tmp/ui_gallery_image.html -w "%{http_code}\n" "$BASE/gallery/image?id=$IMAGE_ID" | grep -q '^200$'
  ! rg -q 'Fatal error|Parse error' /tmp/ui_gallery_image.html
fi

# F1: unknown route 404
curl -sS -o /tmp/ui_404.html -w "%{http_code}\n" "$BASE/no-such-camagru-path" | grep -q '^404$'

# F2: invalid image id returns 404 or 302 (not 200)
curl -sS -o /tmp/ui_badimg.html -w "%{http_code}\n" "$BASE/gallery/image?id=999999999" | grep -qE '^(404|302)$'

# E1: editor without session redirects to login
curl -sS -D /tmp/ui_editor.hdr -o /dev/null "$BASE/editor"
grep -qE '^HTTP/[0-9.]+ 302' /tmp/ui_editor.hdr
grep -qi '^Location: .*/login' /tmp/ui_editor.hdr
```
