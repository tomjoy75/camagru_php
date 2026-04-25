# Feature: `Delivery Compose MVP boot to login page`

Goal  
Provide a minimal Docker Compose setup so the application can be started with one command and the login page is reachable from a clean clone.

Behavior  
Add a `docker-compose.yml` that runs the existing app image/build from the project Dockerfile, publishes the HTTP port, and provides the minimum environment wiring required for startup (`APP_BASE_URL` and `APP_MAIL_*` placeholders/defaults).  
Running `docker compose up` from the repository root should start the app service and make the login route available in a browser.

Constraints  
Keep this feature limited to MVP boot only.  
Do not include persistence hardening or DB bootstrap strategy in this spec (tracked in issue #70).  
Do not include README workflow documentation changes in this spec (tracked in issue #61).  
Reuse the existing Dockerfile baseline from issue #59.

Success Criteria  
From a clean clone, `docker compose up` starts without requiring manual container orchestration steps.  
The app is reachable on the published port and the login page loads successfully.  
The compose definition contains only the minimum required configuration for MVP startup, with advanced persistence/init concerns explicitly deferred.

## Implementation Plan

1 add `docker-compose.yml` with a single app service wired to the existing Dockerfile build
2 expose the HTTP container port with a host port mapping for local browser access
3 add minimal compose environment wiring for `APP_BASE_URL` and `APP_MAIL_*` startup variables
4 run `docker compose up` from project root and confirm the service starts cleanly
5 verify the login page responds on the published host URL/port

## Tests

**Test cases**

- Success: `docker compose up -d --build` starts the app service without startup errors.
- Success: HTTP GET on `/login` returns a success response (200) on the published port.
- Failure: starting compose with an already-used host port fails with a clear bind error.
- Failure: removing required startup env wiring causes app startup/request failure.
- Edge: running `docker compose up -d` twice is idempotent (service remains up).
- Edge: `docker compose down` then `up -d` restores reachability of `/login`.

**Execute tests**

```bash
set -euo pipefail

BASE=http://localhost:8080
ALT_PORT=18080

# ensure clean state
docker compose down --remove-orphans || true

# success: build and start service
docker compose up -d --build

# success: login page reachable
curl -fsS -o /tmp/camagru_login.html -w "HTTP %{http_code}\n" "$BASE/login"

# edge: repeated up is stable
docker compose up -d

# edge: restart cycle restores reachability
docker compose down
docker compose up -d
curl -fsS -o /tmp/camagru_login_after_restart.html -w "HTTP %{http_code}\n" "$BASE/login"

# failure: host port conflict should fail clearly
docker run -d --name camagru-port-conflict -p ${ALT_PORT}:8080 camagru-php:test >/dev/null
docker compose down
APP_PORT=${ALT_PORT} docker compose up -d && { echo "Expected port bind failure"; exit 1; } || true
docker rm -f camagru-port-conflict >/dev/null 2>&1 || true

# cleanup
docker compose down --remove-orphans
```
