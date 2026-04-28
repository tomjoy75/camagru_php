# Feature: `comments_deletion_author_only`

Goal  
Allow a logged-in user to delete their own comments from gallery image detail views, as the smallest moderation slice of issue #31.

Behavior  
- A delete action is available only for comments authored by the current authenticated user.  
- Submitting the delete action removes the targeted comment record from persistence.  
- After deletion, the comment no longer appears in the comment list for that image.  
- A user cannot delete comments authored by other users.  
- Unauthenticated users cannot perform comment deletion.

Constraints  
- Keep implementation within the current MVC boundaries used by this repository (controller authorization + repository data operation + view-level affordance).  
- Enforce authorization on the server side; UI visibility alone is not a security control.  
- Deletion must target a specific comment id and must be protected against forged or malformed requests according to existing project patterns.  
- Stay scoped to deletion only (no edit behavior in this cycle).

Success Criteria  
- Owner can delete their own comment and sees it removed on refresh/navigation back to the image detail view.  
- Non-owner deletion attempts are rejected and do not remove data.  
- Unauthenticated deletion attempts are rejected.  
- Remaining comments and gallery behavior continue to work unchanged.

## Implementation Plan

1 add a dedicated POST route entry for comment deletion on image detail
2 parse and validate deletion inputs (`comment_id`, related `image_id`) in controller action
3 enforce auth and ownership guard before delete operation
4 add repository delete method scoped to `comment_id` + owner `user_id`
5 wire success/failure redirect with existing flash/message mechanism
6 render delete control only for current user comments in image detail view

## Tests

**Test cases**

- Success: authenticated owner deletes their own comment; row is removed from DB.
- Success: owner delete redirects back to image detail (not to `/login`).
- Failure: unauthenticated delete redirects to `/login`; target row remains.
- Failure: authenticated non-owner delete does not remove target row.
- Failure: malformed or missing `comment_id` does not delete any target row.
- Edge: non-existent comment id is handled safely and does not delete unrelated rows.

**Test Setup (authentication)**

```bash
set -euo pipefail

BASE="http://localhost:8080"
DB="database/camagru.db"
OWNER_USER="owner_delete_case"
OWNER_EMAIL="owner_delete_case@example.test"
OWNER_PASS="OwnerDelete123!"
OTHER_USER="other_delete_case"
OTHER_EMAIL="other_delete_case@example.test"
OTHER_PASS="OtherDelete123!"

rm -f /tmp/cookies_owner.txt /tmp/cookies_other.txt

# Ensure two users exist (safe if already registered)
curl -s -X POST "$BASE/register" \
  -d "username=$OWNER_USER&email=$OWNER_EMAIL&password=$OWNER_PASS&confirm_password=$OWNER_PASS" >/dev/null || true
curl -s -X POST "$BASE/register" \
  -d "username=$OTHER_USER&email=$OTHER_EMAIL&password=$OTHER_PASS&confirm_password=$OTHER_PASS" >/dev/null || true

# Confirm email (login requires verified accounts)
OWNER_TOKEN="$(sqlite3 "$DB" "SELECT confirmation_token FROM users WHERE username='$OWNER_USER' LIMIT 1;")"
OTHER_TOKEN="$(sqlite3 "$DB" "SELECT confirmation_token FROM users WHERE username='$OTHER_USER' LIMIT 1;")"
test -n "$OWNER_TOKEN"
test -n "$OTHER_TOKEN"
curl -s "$BASE/register/confirm?token=$OWNER_TOKEN" >/dev/null
curl -s "$BASE/register/confirm?token=$OTHER_TOKEN" >/dev/null

# Login as owner (store owner session only in owner cookie file)
curl -s -c /tmp/cookies_owner.txt -X POST "$BASE/login" \
  -d "username=$OWNER_USER&password=$OWNER_PASS" >/dev/null

# Login as non-owner (separate cookie jar)
curl -s -c /tmp/cookies_other.txt -X POST "$BASE/login" \
  -d "username=$OTHER_USER&password=$OTHER_PASS" >/dev/null

# Am I logged in? (/editor is auth-only and should be 200 when authenticated)
OWNER_EDITOR_CODE="$(curl -s -o /tmp/owner_editor.html -w "%{http_code}" -b /tmp/cookies_owner.txt "$BASE/editor")"
OTHER_EDITOR_CODE="$(curl -s -o /tmp/other_editor.html -w "%{http_code}" -b /tmp/cookies_other.txt "$BASE/editor")"
test "$OWNER_EDITOR_CODE" = "200"
test "$OTHER_EDITOR_CODE" = "200"
```

**Execute tests**

```bash
set -euo pipefail

BASE="http://localhost:8080"
DB="database/camagru.db"

OWNER_ID="$(sqlite3 "$DB" "SELECT id FROM users WHERE username='owner_delete_case' LIMIT 1;")"
OTHER_ID="$(sqlite3 "$DB" "SELECT id FROM users WHERE username='other_delete_case' LIMIT 1;")"
IMAGE_ID="$(sqlite3 "$DB" "SELECT id FROM images ORDER BY id DESC LIMIT 1;")"

test -n "$OWNER_ID"
test -n "$OTHER_ID"
test -n "$IMAGE_ID"

# Seed one owner comment and one other-user comment for controlled checks
sqlite3 "$DB" "INSERT INTO comments (user_id, image_id, content, created_at) VALUES ($OWNER_ID, $IMAGE_ID, 'owner delete target', datetime('now'));"
sqlite3 "$DB" "INSERT INTO comments (user_id, image_id, content, created_at) VALUES ($OTHER_ID, $IMAGE_ID, 'other keep target', datetime('now'));"
OWNER_COMMENT_ID="$(sqlite3 "$DB" "SELECT id FROM comments WHERE user_id=$OWNER_ID AND image_id=$IMAGE_ID AND content='owner delete target' ORDER BY id DESC LIMIT 1;")"
OTHER_COMMENT_ID="$(sqlite3 "$DB" "SELECT id FROM comments WHERE user_id=$OTHER_ID AND image_id=$IMAGE_ID AND content='other keep target' ORDER BY id DESC LIMIT 1;")"

# S1: owner deletes own comment
OWNER_HEADERS="/tmp/delete_owner.headers"
OWNER_CODE="$(curl -s -D "$OWNER_HEADERS" -o /tmp/delete_owner.out -w "%{http_code}" -b /tmp/cookies_owner.txt \
  -X POST "$BASE/comments/delete" \
  -d "comment_id=$OWNER_COMMENT_ID&image_id=$IMAGE_ID")"
echo "$OWNER_CODE" | grep -Eq '^(302|303)$'
grep -Ei '^Location: /gallery/image\\?id='"$IMAGE_ID"'(#comments)?$' "$OWNER_HEADERS" >/dev/null
test "$(sqlite3 "$DB" "SELECT COUNT(*) FROM comments WHERE id=$OWNER_COMMENT_ID;")" = "0"

# S2: non-owner cannot delete other's comment
NON_OWNER_CODE="$(curl -s -o /tmp/delete_non_owner.out -w "%{http_code}" -b /tmp/cookies_other.txt \
  -X POST "$BASE/comments/delete" \
  -d "comment_id=$OTHER_COMMENT_ID&image_id=$IMAGE_ID")"
echo "$NON_OWNER_CODE" | grep -Eq '^(302|303|403)$'
test "$(sqlite3 "$DB" "SELECT COUNT(*) FROM comments WHERE id=$OTHER_COMMENT_ID;")" = "1"

# F1: unauthenticated delete rejected, row remains
GUEST_HEADERS="/tmp/delete_guest.headers"
GUEST_CODE="$(curl -s -D "$GUEST_HEADERS" -o /tmp/delete_guest.out -w "%{http_code}" \
  -X POST "$BASE/comments/delete" \
  -d "comment_id=$OTHER_COMMENT_ID&image_id=$IMAGE_ID")"
echo "$GUEST_CODE" | grep -Eq '^(302|303|401|403)$'
grep -Ei '^Location: /login$' "$GUEST_HEADERS" >/dev/null
test "$(sqlite3 "$DB" "SELECT COUNT(*) FROM comments WHERE id=$OTHER_COMMENT_ID;")" = "1"

# F2: malformed id does not delete unrelated rows
BEFORE_COUNT="$(sqlite3 "$DB" "SELECT COUNT(*) FROM comments WHERE id=$OTHER_COMMENT_ID;")"
BAD_ID_CODE="$(curl -s -o /tmp/delete_bad_id.out -w "%{http_code}" -b /tmp/cookies_owner.txt \
  -X POST "$BASE/comments/delete" \
  -d "comment_id=not_a_number&image_id=$IMAGE_ID")"
echo "$BAD_ID_CODE" | grep -Eq '^(302|303|400|422)$'
AFTER_COUNT="$(sqlite3 "$DB" "SELECT COUNT(*) FROM comments WHERE id=$OTHER_COMMENT_ID;")"
test "$BEFORE_COUNT" = "$AFTER_COUNT"

# E1: missing/non-existent id is safe
BEFORE_SAFE_COUNT="$(sqlite3 "$DB" "SELECT COUNT(*) FROM comments WHERE id=$OTHER_COMMENT_ID;")"
MISSING_ID_CODE="$(curl -s -o /tmp/delete_missing.out -w "%{http_code}" -b /tmp/cookies_owner.txt \
  -X POST "$BASE/comments/delete" \
  -d "image_id=$IMAGE_ID")"
echo "$MISSING_ID_CODE" | grep -Eq '^(302|303|400|422)$'
NON_EXISTENT_CODE="$(curl -s -o /tmp/delete_nonexistent.out -w "%{http_code}" -b /tmp/cookies_owner.txt \
  -X POST "$BASE/comments/delete" \
  -d "comment_id=999999999&image_id=$IMAGE_ID")"
echo "$NON_EXISTENT_CODE" | grep -Eq '^(302|303|404)$'
AFTER_SAFE_COUNT="$(sqlite3 "$DB" "SELECT COUNT(*) FROM comments WHERE id=$OTHER_COMMENT_ID;")"
test "$BEFORE_SAFE_COUNT" = "$AFTER_SAFE_COUNT"
```
