# Feature: `Delivery README Docker workflow`

Goal  
Document a clear, reproducible Docker workflow in `README.md` so evaluators and developers can build and run the app without guessing.

Behavior  
Add a Docker section in the README with exact commands for image build and compose startup/shutdown, required environment variables, and a minimal verification step (login page reachable).  
Document where runtime files persist with the current compose setup and keep wording aligned with the currently implemented behavior.

Constraints  
Keep this feature documentation-only (no application behavior changes).  
Keep it as a single issue scope (`#61`) while staying concise and implementation-oriented.  
Do not invent deployment behavior that is not present in the repository.  
If first-run DB initialization details are not finalized, document only what is currently true and avoid speculative guarantees.

Success Criteria  
README contains a Docker workflow section with copy-paste-ready commands for build, up, down, and basic verification.  
README states required env variables and secrets handling expectations (use `.env`, keep secrets out of git).  
A developer can follow the documented steps from a clean clone and reach the login page.

## Implementation Plan

1 inspect current Docker artifacts (`Dockerfile`, `docker-compose.yml`) and collect exact runnable commands
2 add README subsection for Docker prerequisites and required environment variables/secrets handling
3 add README build/start/stop command sequence for the current compose workflow
4 add README verification steps to confirm app reachability on login page
5 add README note describing current persistence paths and limits of first-run DB initialization guarantees

## Tests

**Test cases**

- Success: README Docker section includes exact commands for build, up, down, and verification.
- Success: README lists required env variables and states secrets should be kept in `.env` (gitignored).
- Failure: documented command not valid from repository root (command/path mismatch).
- Failure: README states DB-init guarantees that are not implemented in current codebase.
- Edge: a clean-clone run following README reaches login page with no undocumented step.
- Edge: persistence note in README matches current mounted paths and does not overclaim behavior.

**Execute tests**

```bash
set -euo pipefail

# 1) docs quality checks
rg "docker compose up|docker compose down|docker compose" README.md
rg "APP_BASE_URL|APP_MAIL_FROM|APP_MAIL_REPLY_TO|\\.env" README.md
rg "database|public/uploads|public/tmp" README.md

# 2) runnable workflow check from repo root (align with documented flow)
docker compose down --remove-orphans || true
docker compose up -d --build
curl -fsS -o /tmp/camagru_readme_login.html -w "HTTP %{http_code}\n" http://localhost:8080/login
docker compose down --remove-orphans
```
