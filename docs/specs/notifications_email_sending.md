# Feature: Notifications email sending (new comment on image)

Tracks [GitHub #37](https://github.com/tomjoy75/camagru_php/issues/37) and `docs/feature_tree.md` (Notifications → email sending). Implements real delivery for the hook described in [GitHub #36](https://github.com/tomjoy75/camagru_php/issues/36) / `docs/specs/notifications_triggering_on_comment.md`: when `CommentNotificationService::notifyImageOwnerOfComment` runs, the image owner receives an email with a link to the commented image. **Out of scope:** in-app notification UI (#38), account email confirmation (#6), password reset mail (#13), changing the comment POST contract.

## Goal

When a **logged-in user** successfully adds a comment on **another user’s** image and the owner’s **`notifications_enabled`** is **on**, send **one email** to the **image owner** (address from the database) that informs them of the new comment and includes a **safe, absolute URL** to the gallery image detail page. Sending mail must remain a **server-side side effect**: comment **HTTP behavior** (302, flashes, saved row) stays as today even if mail fails.

## Behavior

- **Trigger:** Only the existing path after a **successful** `CommentRepository::insert`, when `tryNotifyOnNewComment` decides to call **`notifyImageOwnerOfComment`** (same rules as #36: not self-comment, preference on, etc.). No extra HTTP routes solely for mail.
- **Recipient:** Resolve the owner’s **`email`** from **`users`** by **recipient user id** (the `images.user_id` / owner already implied by #36). If there is no row, empty email, or syntactically invalid address for sending, **skip** the send (log if helpful); **do not** throw to the comment handler.
- **Message:** **Plain text** body is enough. Include at least: that someone commented on their image, the **commenter’s username** (loaded from DB by `commenter_user_id`, not from POST), and a **single clickable URL** to `GET /gallery/image?id=<imageId>` using a **configurable site base** (e.g. environment variable such as **`APP_BASE_URL`** with scheme + host, no trailing slash; document expected shape). Do not embed secrets. Optional short **Subject** line (fixed template + image id or site name).
- **Transport:** Use **PHP standard library only** (e.g. **`mail()`**) with **`From`** / **`Reply-To`** (or equivalent headers) derived from **configuration** (environment variables), not from user input. Invalid or missing **`From`** → skip send safely.
- **Failure isolation:** If **`mail()`** returns false or throws, catch/log and return; the **comment** response must still be success. Never expose mail errors, recipient addresses, or stack traces to the **commenter’s** HTTP response.
- **Idempotence:** One call to **`notifyImageOwnerOfComment`** → at most **one** send attempt (no duplicate `mail()` from that invocation).
- **Development:** Document how operators can verify behavior locally (e.g. **`mail()`** log sink, dev mailbox, or temporary log line behind a flag)—without requiring production SMTP for unit checks.

## Constraints

- **MVC:** **Repositories** load `email` / `username` with **prepared statements**; **service** builds the message and calls **`mail()`**; **controllers** and **views** do not format notification email bodies. No new HTML pages required for this feature.
- **PHP:** Standard library only; no Composer mailer packages.
- **Security:** Recipient and copy (commenter name) come **only** from the **database**. **Do not** put raw HTML in the email from untrusted fields without appropriate plain-text handling (prefer simple lines and encoded URLs). Do not log full message bodies containing PII in production unless explicitly acceptable for the environment.
- **Privacy:** The email must not reveal the **comment body** if you want minimal leakage—or if included, keep it short and plain-text; the spec default is **link-focused** (implementer may add one line of preview only if aligned with product and length limits).

## Success Criteria

- **S1:** With notifications **on** for owner **A**, user **B** posts a valid comment on **A**’s image → comment succeeds (**302**, row in `comments`); **one** email **attempt** is made to **A**’s stored address with a body containing the gallery detail URL for that **`image_id`** and **B**’s username (verify via dev mail capture, test double, or controlled `mail()` wrapper—project-chosen).
- **S2:** Same flow with **A**’s notifications **off** → **no** email attempt (unchanged #36 behavior at the gate).
- **S3:** **A** comments on **A**’s image → **no** email attempt.
- **F1:** Simulate **`mail()`** failure (returns false or throws inside the service) after a successful insert → HTTP outcome for the commenter is unchanged (**302** success path); comment row present.
- **F2:** Owner row missing or **email** unusable → **no** uncaught exception; comment success path unchanged when insert succeeded.
- **E1:** **`APP_BASE_URL`** (or chosen variable) unset or malformed → **no** send or safe skip; **no** **500** on the comment POST path.

## Implementation Plan

1. add `UserRepository` methods to load **`email`** by owner id and **`username`** by commenter id (prepared statements only)
2. add small helpers to read **`APP_BASE_URL`** / **`APP_MAIL_FROM`** (and optional **`APP_MAIL_REPLY_TO`**) from the environment, trim slash on base, and build the absolute **`/gallery/image?id=`** URL; skip when base or **`From`** is missing or invalid
3. in **`CommentNotificationService::notifyImageOwnerOfComment`**, load recipient email and commenter username via the repository; **`filter_var`** recipient; abort quietly if invalid or empty
4. build **plain-text** subject + body (commenter username + image link, no comment body); call **`mail()`** once with **`From`** / **`Reply-To`** headers; **`try/catch`** around send; on failure log-only, no throw; replace the **#36** **`error_log`** id dump with minimal non-PII observability only if still needed
5. document required env vars and a local **`POST /gallery/comment`** verification flow

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Environment variables (operators)

The PHP process must expose:

| Variable | Required | Meaning |
|----------|----------|---------|
| `APP_BASE_URL` | yes for send | Public site root with scheme and host, **no** trailing slash (e.g. `http://localhost:8080`). Must pass `FILTER_VALIDATE_URL` with `http` or `https`. |
| `APP_MAIL_FROM` | yes for send | Sender address for `mail()` **`From`**; must be a valid email. |
| `APP_MAIL_REPLY_TO` | no | Optional **`Reply-To`**; if set and invalid, it is ignored. |

**Example (built-in server):** from the repo root, with a DB path your app already uses:

```bash
export APP_BASE_URL=http://localhost:8080
export APP_MAIL_FROM=camagru@example.com
php -S localhost:8080 -t public
```

If `APP_BASE_URL` or `APP_MAIL_FROM` is missing or invalid, the comment is still saved and **`notifyImageOwnerOfComment`** returns without calling **`mail()`**. Check logs for **`camagru_notify_mail_ok`**, **`camagru_notify_mail_failed`**, or **`camagru_notify_mail_exception`** (image/comment ids only).

Verification: use **`## Tests`** **Test Setup** + **Execute tests** and your local MTA or syslog where **`mail()`** is routed.

## Tests

### Test cases

**Success**

- **S1:** User **B** posts a valid comment on an image owned by **A**; **A** has `notifications_enabled = 1`, a **syntactically valid** `users.email`, and the app process has valid **`APP_BASE_URL`** + **`APP_MAIL_FROM`** → **302**; new `comments` row; **one** `mail()` attempt with body containing **`B`’s username** and an absolute URL to `/gallery/image?id=<imageId>` (verify via local MTA log, inbox, or an implementation-defined **non-PII** log line—no addresses in logs).
- **S2:** Same as **S1** but **A**’s `notifications_enabled = 0` → **302**; comment saved; **no** `mail()` attempt.
- **S3:** **A** comments on **A**’s own image → **302**; **no** `mail()` attempt.

**Failure**

- **F1:** **`mail()`** returns **false** or throws inside the notification path after a successful insert → commenter still gets **302** + success behavior; comment row exists.
- **F2:** Owner **`email`** empty or fails **`FILTER_VALIDATE_EMAIL`** → **no** throw; **302** + saved comment when insert succeeds.

**Edge**

- **E1:** **`APP_BASE_URL`** unset or not usable for an absolute URL → **no** send; **302** + saved comment when insert succeeds.
- **E2:** **`APP_MAIL_FROM`** unset or invalid → **no** send; **302** + saved comment when insert succeeds.

### Test Setup (authentication)

Run the **PHP built-in server** (or your stack) with mail-related env vars set, for example:

`APP_BASE_URL=http://localhost:8080`  
`APP_MAIL_FROM=camagru@example.com`  
(optional) `APP_MAIL_REPLY_TO=support@example.com`

```bash
rm -f cookies_b.txt
BASE=http://localhost:8080
COMMENT_POST="${BASE}/gallery/comment"
DATABASE_PATH=${DATABASE_PATH:-database/camagru.db}

read -r IMAGE_ID OWNER_ID <<EOF
$(sqlite3 "$DATABASE_PATH" "SELECT id, user_id FROM images ORDER BY id LIMIT 1;")
EOF

STAMP=$(date +%s)
EMAIL_B="notify_mail_${STAMP}@example.com"
USER_B="notifymail${STAMP}"
PASS='Testpass1!'

curl -s -o /dev/null -c cookies_b.txt -X POST "$BASE/register" \
  -d "email=$EMAIL_B" -d "username=$USER_B" -d "password=$PASS" -d "confirm_password=$PASS"
curl -s -o /dev/null -c cookies_b.txt -b cookies_b.txt -L -X POST "$BASE/login" \
  -d "email=$EMAIL_B" -d "password=$PASS"

# Owner email must be valid for S1 mail path (adjust local part if collides)
sqlite3 "$DATABASE_PATH" "UPDATE users SET email = 'owner_${STAMP}@example.com' WHERE id = ${OWNER_ID};"
sqlite3 "$DATABASE_PATH" "UPDATE users SET notifications_enabled = 1 WHERE id = ${OWNER_ID};"
```

Requires a running server, a writable DB, and **`B` ≠ `OWNER_ID`** (if equal, pick another `images` row or register a conflicting scenario manually).

### Execute tests

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
  echo "SKIP: need IMAGE_ID and cookies_b.txt from Test Setup"
  exit 0
fi

BODY="notify_mail_${RANDOM}_$(date +%s)"

# S1 — owner notify on; confirm mail via MTA / inbox / approved non-PII hook
sqlite3 "$DATABASE_PATH" "UPDATE users SET notifications_enabled = 1 WHERE id = ${OWNER_ID};"
before=$(comment_count "$IMAGE_ID")
code=$(curl -s -o /dev/null -w "%{http_code}" -b cookies_b.txt -X POST "$COMMENT_POST" \
  -d "image_id=$IMAGE_ID" -d "content=$BODY")
after=$(comment_count "$IMAGE_ID")
echo "S1 http=$code count $before->$after (expect 302, after>before)"

# S2 — gate off; no mail
sqlite3 "$DATABASE_PATH" "UPDATE users SET notifications_enabled = 0 WHERE id = ${OWNER_ID};"
curl -s -o /dev/null -w "S2 http=%{http_code}\n" -b cookies_b.txt -X POST "$COMMENT_POST" \
  -d "image_id=$IMAGE_ID" -d "content=${BODY}_s2"

# F2 — invalid owner email; restore after
sqlite3 "$DATABASE_PATH" "UPDATE users SET email = 'not-an-email', notifications_enabled = 1 WHERE id = ${OWNER_ID};"
curl -s -o /dev/null -w "F2 http=%{http_code}\n" -b cookies_b.txt -X POST "$COMMENT_POST" \
  -d "image_id=$IMAGE_ID" -d "content=${BODY}_f2"
sqlite3 "$DATABASE_PATH" "UPDATE users SET email = 'owner_restore_${RANDOM}@example.com' WHERE id = ${OWNER_ID};"
```

**S3:** Log in as **owner** **`OWNER_ID`**, **`POST /gallery/comment`** on that user’s image → **302**, no mail.

**F1:** Force **`mail()`** false/throw in the service; **`POST`** again → **302**, new row; revert.

**E1 / E2:** Restart app without **`APP_BASE_URL`** / **`APP_MAIL_FROM`**; **`POST`** → **302**, new row, no send.

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)
