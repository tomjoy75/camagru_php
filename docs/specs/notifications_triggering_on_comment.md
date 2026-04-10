# Feature: Notifications triggering on new comment

Tracks [GitHub #36](https://github.com/tomjoy75/camagru_php/issues/36) and `docs/feature_tree.md` (Notifications → triggering: on new comment, respect image author preference). Builds on the existing `POST /gallery/comment` flow (`GalleryController::addComment`), `CommentRepository::insert`, and `users.notifications_enabled` ([GitHub #35](https://github.com/tomjoy75/camagru_php/issues/35)). **Out of scope for this feature spec:** composing or sending email bodies, SMTP/`mail()` configuration, and link templates ([GitHub #37](https://github.com/tomjoy75/camagru_php/issues/37)); in-app notification UI (#38).

## Goal

After a **successful** comment insert, decide whether the **image owner** should receive a **comment notification**, using only server-side data and the owner’s **`notifications_enabled`** flag, then **invoke a single delivery entry point** (service) so that email or other transport can be plugged in under #37 without changing the comment HTTP contract. The commenter must always get the same success behavior (redirect and flash) when the comment row is stored, even if notification handling fails.

## Behavior

- **When:** Run **only** after `CommentRepository::insert` returns a **positive** comment id (same request as today’s successful comment POST). Do **not** run on validation failures, missing image, guest requests, or failed inserts.
- **Who is notified:** The **owner** of the image is `images.user_id` for the commented `image_id` (same row already validated for existence before insert). If that owner id cannot be resolved, **skip** notification (no error shown to the commenter).
- **Self-comments:** If `owner_user_id === commenter_user_id` (session user who posted the comment), **do not** notify. Owners commenting on their own image must never trigger this path.
- **Preference:** Load **`notifications_enabled`** for the owner (e.g. via `UserRepository::getNotificationsEnabledByUserId`). If the value is **0**, **skip** notification. If **1**, proceed to the delivery entry point. If the user row is missing, treat as **skip** (safe default).
- **Delivery entry point:** Call a dedicated **service** method (e.g. on a small `CommentNotificationService` or equivalent) with enough **non-sensitive** context to support #37 (at minimum: recipient user id, image id, comment id, commenter user id; recipient **email** may be loaded inside the service or in #37—choose one place and keep repositories SQL-only). The service **must not** render HTML.
- **Failure isolation:** Exceptions or errors **inside** notification logic **must not** replace the successful comment response: the comment remains saved, the client still receives **302** to the image detail with the usual success flash. Log or swallow per project convention; never expose stack traces, SQL, or internal paths to the client.
- **Idempotence:** One successful insert → at most **one** notification attempt for that event (no duplicate triggers from the same handler invocation).

## Constraints

- **MVC:** Controller orchestrates after insert; **business rules** (self-comment, preference, “should notify”) live in a **service**; **repositories** remain data access only; **views** unchanged for this feature.
- **PHP:** Standard library only; **prepared statements** for any new queries; no new dependencies.
- **Security:** Recipient identity and email come from the **database**, not from POST. Do not leak whether an email was sent or the owner’s address to the commenter in HTTP responses. Do not trust client-supplied user ids for “who to notify.”
- **Compatibility:** No change to the public **POST** `/gallery/comment` contract (fields, redirects, flash keys) except for additive server-side side effects after success.

## Success Criteria

- **S1:** When user **A** posts a valid comment on an image owned by user **B**, with **B**’s `notifications_enabled = 1`, and **A ≠ B**, the **delivery entry point** is invoked exactly once with correct ids (observable via test double, logging in dev, or—once #37 exists—an actual email attempt).
- **S2:** Same as **S1** but **B**’s `notifications_enabled = 0` → delivery entry point is **not** invoked.
- **S3:** Owner comments on their **own** image (valid insert) → delivery entry point is **not** invoked.
- **F1:** Simulated failure inside the notification service (e.g. thrown exception or false return) after a successful insert → HTTP response is still **302** to the image detail with success flash; comment row exists in `comments`.
- **F2:** Insert fails (`insert` returns `null`) → delivery entry point is **never** invoked; existing error flash/redirect behavior unchanged.

## Implementation Plan

1. add `src/service/CommentNotificationService.php` with no-op `notifyImageOwnerOfComment` (recipient user id, image id, comment id, commenter user id)
2. add pure decision helper on the same service (self-comment off, `notifications_enabled` 0/1/null rules)
3. add `tryNotifyOnNewComment` (or equivalent) that try/catches internally, loads owner flag via `UserRepository`, applies the helper, calls `notifyImageOwnerOfComment` only when allowed
4. in `GalleryController::addComment`, keep `images.user_id` from the existing `findById` result used for the pre-insert existence check
5. after a successful `CommentRepository::insert`, require the service and call `tryNotifyOnNewComment` before setting the success flash and redirect

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

### Test cases

**Success**

- **S1:** User **B** posts a valid comment on an image owned by **A** with **A**’s `notifications_enabled = 1` and **A ≠ B** → comment saved (**302** to detail); **delivery entry point invoked once** with correct ids (see **Observable hook** below).
- **S2:** Same as **S1** but **A**’s `notifications_enabled = 0` → comment saved; **delivery entry point not** invoked.
- **S3:** **A** comments on **A**’s own image → comment saved; **delivery entry point not** invoked.

**Failure**

- **F1:** Notification logic throws (temporary dev fault injection) after successful insert → still **302** to detail with success flash; new `comments` row exists.
- **F2:** `CommentRepository::insert` returns `null` → existing error path; **delivery entry point never** invoked (manual or controlled DB fault).

**Edge**

- **E1:** Owner’s `getNotificationsEnabledByUserId` yields **null** (missing user) → **skip** notify; comment success path unchanged if comment insert succeeded.
- **E2:** Two valid `POST`s in a row as **B** on the same image → two rows; **two** delivery attempts (one per successful insert), no duplicate within a **single** handler call.

**Observable hook (pick one for S1–S2 automation):** e.g. single `error_log` line inside `notifyImageOwnerOfComment` with ids (dev-only or behind `APP_DEBUG`), a dedicated temporary test-only include, or manual step-through until #37 sends mail.

**Pure logic:** expose a **public static** boolean helper (e.g. `shouldNotify…(ownerId, commenterId, enabled)`) so **S2/S3/E1** are checkable without HTTP; keep it aligned with the service rules in **Behavior**.

### Test Setup (authentication)

```bash
rm -f cookies_b.txt
BASE=http://localhost:8080
COMMENT_POST="${BASE}/gallery/comment"
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}

# Image under test: first row in images (adjust query if your seed differs)
read -r IMAGE_ID OWNER_ID <<EOF
$(sqlite3 "$DATABASE_PATH" "SELECT id, user_id FROM images ORDER BY id LIMIT 1;")
EOF

STAMP=$(date +%s)
EMAIL_B="notif_trig_${STAMP}@example.com"
USER_B="notiftrig${STAMP}"
PASS='Testpass1!'

curl -s -o /dev/null -c cookies_b.txt -X POST "$BASE/register" \
  -d "email=$EMAIL_B" -d "username=$USER_B" -d "password=$PASS" -d "confirm_password=$PASS"
curl -s -o /dev/null -c cookies_b.txt -b cookies_b.txt -L -X POST "$BASE/login" \
  -d "username=$USER_B" -d "password=$PASS"
```

Requires at least one `images` row and a running server. If `IMAGE_ID` is empty, skip HTTP blocks.

### Execute tests

**1) Pure helper (from repository root; adjust class/method name to match implementation)**

```bash
cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
php -r '
require "src/service/CommentNotificationService.php";
// Adjust to the actual public static helper name/signature:
$c = CommentNotificationService::class;
$m = "shouldNotify"; // e.g. shouldNotify(int owner, int commenter, ?int enabled)
if (!is_callable([$c, $m])) { fwrite(STDERR, "SKIP: define public static $m on CommentNotificationService\n"); exit(0); }
$call = [$c, $m];
assert(!$call(1, 1, 1), "self-comment");
assert(!$call(2, 1, 0), "disabled");
assert(!$call(2, 1, null), "null enabled");
assert($call(2, 1, 1), "other user enabled");
echo "shouldNotify OK\n";
'
```

**2) HTTP + SQLite (regression + preference branch; B must not equal OWNER_ID)**

```bash
BASE=http://localhost:8080
COMMENT_POST="${BASE}/gallery/comment"
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}
read -r IMAGE_ID OWNER_ID <<EOF
$(sqlite3 "$DATABASE_PATH" "SELECT id, user_id FROM images ORDER BY id LIMIT 1;")
EOF

comment_count() {
  curl -s -b cookies_b.txt "$BASE/gallery/image?id=$1" | tr '\n' ' ' | sed -n 's/.*Comments:<\/dt>[[:space:]]*<dd[^>]*>[[:space:]]*\([0-9][0-9]*\).*/\1/p' | head -1
}

if [ -z "$IMAGE_ID" ] || [ ! -s cookies_b.txt ]; then
  echo "SKIP HTTP: need IMAGE_ID and cookies_b.txt from Test Setup"
  exit 0
fi

BODY="notif_trig_${RANDOM}_$(date +%s)"

# Owner notifications on → expect 302 success (observe notify hook separately)
sqlite3 "$DATABASE_PATH" "UPDATE users SET notifications_enabled = 1 WHERE id = ${OWNER_ID};"
before=$(comment_count "$IMAGE_ID")
code=$(curl -s -o /dev/null -w "%{http_code}" -D /tmp/notif_hdr.txt -b cookies_b.txt -X POST "$COMMENT_POST" \
  -d "image_id=$IMAGE_ID" -d "content=$BODY")
grep -i '^location:' /tmp/notif_hdr.txt || true
after=$(comment_count "$IMAGE_ID")
echo "S1/S2 branch on HTTP: POST http=$code count_before=$before count_after=$after (expect 302, after>before if new comment)"

# Owner notifications off → still 302 and count increases (no user-visible failure)
sqlite3 "$DATABASE_PATH" "UPDATE users SET notifications_enabled = 0 WHERE id = ${OWNER_ID};"
before2=$(comment_count "$IMAGE_ID")
curl -s -o /dev/null -w "POST with owner notify off -> %{http_code}\n" -b cookies_b.txt -X POST "$COMMENT_POST" \
  -d "image_id=$IMAGE_ID" -d "content=${BODY}_off"
after2=$(comment_count "$IMAGE_ID")
echo "count_before=$before2 count_after=$after2"

# E2 — second POST in a row
curl -s -o /dev/null -b cookies_b.txt -X POST "$COMMENT_POST" \
  -d "image_id=$IMAGE_ID" -d "content=${BODY}_e2"
after3=$(comment_count "$IMAGE_ID")
echo "E2 third count=$after3 (expect +1 vs after=$after2)"
```

**3) F1 / S3 / F2**

- **F1:** Temporarily throw inside `notifyImageOwnerOfComment`, repeat a successful comment `POST` → expect **302** and new comment row; remove throw after.
- **S3:** Log in as the **owner** of `IMAGE_ID` (account with `users.id = OWNER_ID`), `POST` a comment on that image → **302**; confirm notify hook **not** fired.
- **F2:** Not practical via curl alone; use a controlled failure in `CommentRepository::insert` or a test double in a dedicated PHP test script.

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)
