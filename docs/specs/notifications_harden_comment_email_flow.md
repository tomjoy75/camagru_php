# Feature: Notifications harden comment email notification flow

Goal  
Ensure the mandatory notification behavior is reliable and 42-compliant: when a new comment is posted, the image owner receives an email notification attempt only under valid conditions, without affecting comment creation success.

Behavior  
After a successful comment creation on a gallery image, the system evaluates notification eligibility using existing rules and user preference.  
If the image owner has notifications enabled and the commenter is not the owner, one email notification attempt is triggered.  
If notifications are disabled or the commenter is the image owner, no email notification attempt is made.  
If email transport fails, the comment creation flow still succeeds and the user-facing response remains successful.

Constraints  
Stay within current MVC architecture and existing notification flow (`CommentNotificationService`, user preference in `users.notifications_enabled`).  
Do not add a notifications table, unread/read state, or in-app notification center behavior.  
Do not add header badge/count logic in this feature.  
Keep scope limited to hardening/verification of existing comment email notification behavior and associated test coverage.

Success Criteria  
Comment notifications follow the expected decision matrix (enabled/disabled, owner/non-owner) with no regressions.  
Comment creation remains successful even when email sending fails.  
Self-comment path does not trigger notification attempt.  
Targeted manual/curl verification steps exist and are reproducible for these cases.

## Implementation Plan

1 extract and lock the notification decision matrix in focused service-level checks for enabled disabled and self-comment cases
2 ensure gallery comment success flow always returns success redirect even when notification sending fails
3 verify notification preference integration uses users.notifications_enabled consistently in the notification trigger path
4 add or tighten observable log outcomes for attempted skipped and failed notification paths without exposing recipient data
5 add targeted manual and curl-based verification steps covering enabled off self-comment and transport-failure scenarios

## Tests

**Test cases**

- Success: commenter posts on another user's image with owner `notifications_enabled=1` -> comment succeeds and one notification attempt is logged.
- Success: commenter posts on another user's image with owner `notifications_enabled=0` -> comment succeeds and notification is skipped.
- Failure path: mail transport unavailable/misconfigured -> comment still succeeds; failure is log-only.
- Edge: image owner comments on own image -> comment succeeds and notification is skipped.
- Edge: missing/invalid comment payload -> request is rejected by comment validation; no notification attempt.

**Test Setup (authentication)**

```bash
set -euo pipefail

BASE_URL="http://127.0.0.1:8080"
OWNER_COOKIE="owner.cookies.txt"
OTHER_COOKIE="other.cookies.txt"
DB_PATH="database/camagru.db"

rm -f "$OWNER_COOKIE" "$OTHER_COOKIE"

# Ensure two authenticated users exist (owner / other).
# If already created, keep existing accounts and only login.
OWNER_USERNAME="notif_owner"
OWNER_EMAIL="notif_owner@example.com"
OWNER_PASSWORD="Password1!"
OTHER_USERNAME="notif_other"
OTHER_EMAIL="notif_other@example.com"
OTHER_PASSWORD="Password1!"

curl -sS -i -c "$OWNER_COOKIE" -X POST "$BASE_URL/register" \
  --data-urlencode "email=$OWNER_EMAIL" \
  --data-urlencode "username=$OWNER_USERNAME" \
  --data-urlencode "password=$OWNER_PASSWORD" \
  --data-urlencode "confirm_password=$OWNER_PASSWORD" >/dev/null || true

curl -sS -i -c "$OTHER_COOKIE" -X POST "$BASE_URL/register" \
  --data-urlencode "email=$OTHER_EMAIL" \
  --data-urlencode "username=$OTHER_USERNAME" \
  --data-urlencode "password=$OTHER_PASSWORD" \
  --data-urlencode "confirm_password=$OTHER_PASSWORD" >/dev/null || true

curl -sS -i -c "$OWNER_COOKIE" -X POST "$BASE_URL/login" \
  --data-urlencode "username=$OWNER_USERNAME" \
  --data-urlencode "password=$OWNER_PASSWORD" >/dev/null

curl -sS -i -c "$OTHER_COOKIE" -X POST "$BASE_URL/login" \
  --data-urlencode "username=$OTHER_USERNAME" \
  --data-urlencode "password=$OTHER_PASSWORD" >/dev/null

# For environments that require confirmed email before login.
sqlite3 "$DB_PATH" "UPDATE users SET email_verified=1 WHERE username IN ('$OWNER_USERNAME', '$OTHER_USERNAME');"

# Refresh login cookies after forcing verified state.
curl -sS -i -c "$OWNER_COOKIE" -X POST "$BASE_URL/login" \
  --data-urlencode "username=$OWNER_USERNAME" \
  --data-urlencode "password=$OWNER_PASSWORD" >/dev/null
curl -sS -i -c "$OTHER_COOKIE" -X POST "$BASE_URL/login" \
  --data-urlencode "username=$OTHER_USERNAME" \
  --data-urlencode "password=$OTHER_PASSWORD" >/dev/null

# Auth probe (must not redirect to /login).
curl -sS -D - -o /dev/null -b "$OWNER_COOKIE" "$BASE_URL/editor" | grep -E '^HTTP/|^Location:'
curl -sS -D - -o /dev/null -b "$OTHER_COOKIE" "$BASE_URL/editor" | grep -E '^HTTP/|^Location:'

# Prerequisite: one image owned by notif_owner must exist.
# If missing, create one owner image by uploading a local fixture through the editor as owner.
IMAGE_ID="$(sqlite3 "$DB_PATH" "SELECT i.id FROM images i JOIN users u ON u.id=i.user_id WHERE u.username='${OWNER_USERNAME}' ORDER BY i.id DESC LIMIT 1;")"
test -n "$IMAGE_ID"
echo "Using IMAGE_ID=$IMAGE_ID"
```

**Execute tests**

```bash
set -euo pipefail

BASE_URL="http://127.0.0.1:8080"
OWNER_COOKIE="owner.cookies.txt"
OTHER_COOKIE="other.cookies.txt"
DB_PATH="database/camagru.db"
IMAGE_ID="${IMAGE_ID:?set IMAGE_ID from setup}"

# S1: owner notifications ON + other user comment -> success redirect expected.
sqlite3 "$DB_PATH" "UPDATE users SET notifications_enabled=1 WHERE username='notif_owner';"
curl -sS -i -b "$OTHER_COOKIE" -X POST "$BASE_URL/gallery/comment" \
  --data-urlencode "image_id=$IMAGE_ID" \
  --data-urlencode "content=S1 other user comment with owner notifications on" | grep -E "^HTTP/|^Location:"

# S2: owner notifications OFF + other user comment -> success redirect expected, no notify attempt expected in logs.
sqlite3 "$DB_PATH" "UPDATE users SET notifications_enabled=0 WHERE username='notif_owner';"
curl -sS -i -b "$OTHER_COOKIE" -X POST "$BASE_URL/gallery/comment" \
  --data-urlencode "image_id=$IMAGE_ID" \
  --data-urlencode "content=S2 other user comment with owner notifications off" | grep -E "^HTTP/|^Location:"

# E1: self-comment by owner -> success redirect expected, notification skipped.
sqlite3 "$DB_PATH" "UPDATE users SET notifications_enabled=1 WHERE username='notif_owner';"
curl -sS -i -b "$OWNER_COOKIE" -X POST "$BASE_URL/gallery/comment" \
  --data-urlencode "image_id=$IMAGE_ID" \
  --data-urlencode "content=E1 owner self comment" | grep -E "^HTTP/|^Location:"

# F1: invalid payload -> redirect with comment error path, no notification attempt.
curl -sS -i -b "$OTHER_COOKIE" -X POST "$BASE_URL/gallery/comment" \
  --data-urlencode "image_id=$IMAGE_ID" \
  --data-urlencode "content=   " | grep -E "^HTTP/|^Location:"

# F2: mail transport failure isolation (manual assertion):
# run the app with mail transport unavailable, then repeat S1 and confirm:
# - HTTP success redirect remains identical
# - app logs contain `camagru_notify_mail_attempt` then a failure marker
#   (`camagru_notify_mail_failed` or `camagru_notify_mail_exception`) while comment still posts
```
