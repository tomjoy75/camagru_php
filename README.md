## Docker workflow (local)

### Prerequisites

- Docker with Compose plugin (`docker compose` command available)
- Run commands from repository root

### Environment variables and secrets

Create a local `.env` file (do not commit secrets):

```bash
cat > .env <<'EOF'
APP_PORT=8080
APP_BASE_URL=http://localhost:8080
APP_MAIL_FROM=camagru@example.com
# optional
# APP_MAIL_REPLY_TO=support@example.com
EOF
```

### Build and start

```bash
docker compose up -d --build
```

### Verify app reachability

```bash
curl -fsS -o /tmp/camagru_login.html -w "HTTP %{http_code}\n" http://localhost:8080/login
```

Expected result: `HTTP 200`.

### Stop and cleanup

```bash
docker compose down --remove-orphans
```

### Optional: local email with Mailpit (Docker Hub)

Use this when you want to **see** outbound mail (comment notifications, registration, password reset) in a **web inbox** during local Docker runs. The application still uses PHP `mail()`; the image can relay through [Mailpit](https://github.com/axllent/mailpit) via `msmtp` when you opt in.

1. Start the stack with the `**mail`** Compose profile and point the app at the Mailpit service:
  ```bash
   export MAILPIT_HOST=mailpit
   docker compose --profile mail up -d --build
  ```
   Or add `MAILPIT_HOST=mailpit` to your `.env` **only** while you use this workflow (leave it unset for the default app-only compose so `mail()` is not sent to a missing host).
2. Open the Mailpit UI: **[http://localhost:8025](http://localhost:8025)** (override with `MAILPIT_UI_PORT` in `.env` if 8025 is taken).
3. Trigger any flow that sends mail (e.g. post a comment on another user’s image with notifications on). Messages should appear in Mailpit. SMTP in the container listens on service port **1025**; the app container connects to `mailpit:1025` when `MAILPIT_HOST=mailpit` is set.

`docker compose up` (without `--profile mail`) is unchanged: only the `app` service runs, and in-container `mail()` behavior matches a typical dev image without a local MTA.

### Persistence notes (current behavior)

- Current compose mounts persist data from:
  - `./database` -> `/app/database`
  - `./public/uploads` -> `/app/public/uploads`
  - `./public/tmp` -> `/app/public/tmp`
- This README documents current implemented behavior only.
- First-run DB initialization guarantees are not specified here beyond what is currently implemented.

## Comment notification email (dev / verification)

### Why this design

The subject calls for **server-side** notification when a comment is posted. The app implements that with PHP’s **standard library** (`mail()`), keeps **secrets and sender/base URL out of code** via environment variables, and leaves **actual email delivery** to whatever **MTA or sendmail-compatible transport** the host provides. That is appropriate for Camagru: the project is the **web feature**, not operating a full SMTP service.

### What is implemented

When a **valid comment** is saved on **another user’s** image and the image owner has **notifications enabled**, the application **attempts exactly one** plain-text email to the owner’s address from the database, using `**APP_BASE_URL`**, `**APP_MAIL_FROM**`, and optionally `**APP_MAIL_REPLY_TO**`. If `mail()` fails or throws, the **comment still succeeds** (same HTTP success path as without mail); errors are **log-only**, not exposed to the commenter.

### How to verify

1. **Export** (adjust values as needed):
  ```bash
   export APP_BASE_URL='http://127.0.0.1:8080'
   export APP_MAIL_FROM='camagru@example.com'
   # optional:
   # export APP_MAIL_REPLY_TO='support@example.com'
  ```
2. **Start PHP** from the **repository root** so env applies to the process, e.g.:
  ```bash
   php -S 127.0.0.1:8080 public/index.php
  ```
3. **Reproduce:** log in as user **B**, post a comment on an image owned by user **A**, with **A** having notifications **on** and **B ≠ A**.
4. **Logs** (stderr / terminal where `php -S` runs): expect `**camagru_notify_mail_ok`** on a successful handoff to the local transport, or `**camagru_notify_mail_failed**` / `**camagru_notify_mail_exception**` when transport or `mail()` fails. **Seeing `mail_ok` means PHP passed the message to the local MTA**, not that the message reached an inbox (relay, DNS, spam filters, etc. can still block delivery).
5. **If a local MTA** (e.g. Postfix/sendmail) **is** installed, you may inspect the queue or logs, e.g. `postqueue -p` and `sudo tail -n 50 /var/log/mail.log` (paths may differ by distro).

**Honest split:** Log lines (**especially when `mail()` runs but transport is missing**, e.g. `/usr/sbin/sendmail: not found`) are enough to **validate the application path** and failure isolation. **Inbox delivery** is only confirmed when your environment has a working **sendmail-compatible** path or you use a proper test relay. Details and test ideas: `docs/specs/notifications_email_sending.md`.

## Resources

- Sticker assets source: [purepng.com](https://purepng.com/)

TODO:   
- Delete mustache sticker and add a new one (and other stickers.