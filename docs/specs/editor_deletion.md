# Feature: Editor deletion

## Goal

Allow an authenticated user to delete one of their own previously saved editor images so they can manage their content and remove unwanted posts.

## Behavior

- Deletion is available only for authenticated users in the editor area.
- A delete action targets one saved image selected by id.
- If the image belongs to the current user, the application removes:
  - the database record for that image
  - the corresponding image file from storage
- After a successful deletion, the user is redirected back to the editor with a visible success feedback.
- If the image does not exist, does not belong to the current user, or deletion fails, the application returns a safe error flow without exposing internal details.
- Public gallery and other users' images are not modified beyond the removed owned image.

## Constraints

- Respect current MVC responsibilities from `docs/ARCHITECT.md`:
  - controller handles HTTP input/response and redirects
  - service contains ownership and deletion business rules
  - view renders feedback only
- Enforce ownership: users can delete only images they own.
- Treat image id and request data as untrusted input; validate server-side.
- Keep SQL parameterized and avoid leaking SQL errors or filesystem paths in user-facing messages.
- Keep scope limited to editor image deletion (no bulk delete, no admin moderation, no gallery refactor).

## Success Criteria

- Authenticated user can delete one of their own saved images from the editor flow.
- Deleting an owned image removes both DB row and file, and the image no longer appears in the user's editor sidebar/public listing.
- Attempt to delete another user's image is refused safely and leaves data unchanged.
- Invalid/nonexistent image id does not crash the app and follows the expected safe error behavior.
- Manual verification confirms no internal error details are exposed to end users.

## Implementation Plan

1 add delete route wiring for editor image deletion
2 validate request method, auth session, and image id input
3 add repository method to load image ownership and file path by id
4 add service method to enforce ownership and delete DB record + file
5 call deletion service from controller and redirect with success/error flash
6 update editor view to expose delete action and render feedback message

## Tests

**Test cases**

**Success**

- **S1:** Authenticated owner deletes one of their own saved images from editor; request is accepted and redirects back to `/editor`.
- **S2:** After deletion, the removed image no longer appears in the owner "Previous images" sidebar.
- **S3:** After deletion, direct GET of the removed image under `/uploads/...` returns not found.

**Failure**

- **F1:** Unauthenticated deletion attempt is rejected (redirect to `/login` or equivalent auth-safe behavior).
- **F2:** Authenticated user attempts to delete another user's image; operation is refused and target image remains available.
- **F3:** Nonexistent image id is handled safely (no 500, no SQL/path leakage).

**Edge**

- **E1:** Invalid id formats (`''`, `abc`, `-1`, `0`) are handled safely with no crash.
- **E2:** Repeating deletion on the same id after successful deletion remains safe and idempotent from user perspective (no uncaught error).
- **E3:** Wrong HTTP method on delete route (e.g. GET) is rejected by router/controller method constraints.

**Test Setup (authentication)**

```bash
BASE=http://localhost:8080
rm -f /tmp/camagru_u1.cookies /tmp/camagru_u2.cookies

# Register + login user 1 (owner)
curl -i -c /tmp/camagru_u1.cookies -X POST "$BASE/register" \
  -d "email=u1@example.com&username=user1&password=Password123!&confirm_password=Password123!"
curl -i -c /tmp/camagru_u1.cookies -X POST "$BASE/login" \
  -d "username=user1&password=Password123!"

# Register + login user 2 (non-owner)
curl -i -c /tmp/camagru_u2.cookies -X POST "$BASE/register" \
  -d "email=u2@example.com&username=user2&password=Password123!&confirm_password=Password123!"
curl -i -c /tmp/camagru_u2.cookies -X POST "$BASE/login" \
  -d "username=user2&password=Password123!"
```

**Execute tests**

```bash
BASE=http://localhost:8080

# Precondition: create one saved image as user1 in the editor flow, then capture its image id from DB/UI.
# Set these before running:
OWNER_IMAGE_ID=1
OWNER_IMAGE_SRC="/uploads/example.png"

# S1: owner delete request (expect 302 to /editor)
curl -i -b /tmp/camagru_u1.cookies -X POST "$BASE/editor/delete" \
  -d "image_id=$OWNER_IMAGE_ID"

# S2: deleted image no longer listed in owner editor page (expect no match)
curl -s -b /tmp/camagru_u1.cookies "$BASE/editor" | grep -F "$OWNER_IMAGE_SRC" && echo "S2 FAIL" || echo "S2 OK"

# S3: removed file no longer directly accessible (expect 404)
curl -s -o /dev/null -w "%{http_code}\n" "$BASE$OWNER_IMAGE_SRC"

# F1: unauthenticated delete attempt (expect redirect/auth-safe behavior)
curl -i -X POST "$BASE/editor/delete" -d "image_id=$OWNER_IMAGE_ID"

# F2: non-owner delete attempt (expect refused; target remains)
curl -i -b /tmp/camagru_u2.cookies -X POST "$BASE/editor/delete" \
  -d "image_id=$OWNER_IMAGE_ID"

# F3 / E1: nonexistent + invalid ids are safe (expect no 500)
for id in 999999 "" abc -1 0; do
  curl -i -b /tmp/camagru_u1.cookies -X POST "$BASE/editor/delete" -d "image_id=$id"
done

# E2: repeat delete remains safe (expect no 500)
curl -i -b /tmp/camagru_u1.cookies -X POST "$BASE/editor/delete" -d "image_id=$OWNER_IMAGE_ID"

# E3: wrong method on delete route (expect not found or method-safe rejection)
curl -i "$BASE/editor/delete"
```
