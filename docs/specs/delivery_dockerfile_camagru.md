# Feature: `delivery_dockerfile_camagru`

Goal
Describe the objective.

Provide a Docker image definition for Camagru that can run the PHP application with SQLite and GD support, so the project has a containerized deployment baseline aligned with subject requirements.

Behavior
Describe expected behavior.

The repository contains a Dockerfile that builds a runnable image with required PHP extensions (`pdo_sqlite`, `gd`) and serves the application entrypoint from `public/index.php`. The container starts without manual in-container setup steps outside documented runtime configuration.

Constraints
Technical or security constraints.

Keep scope limited to the Dockerfile baseline only (no compose orchestration or full README workflow in this feature). Do not hardcode secrets in image layers. Preserve compatibility with existing project structure and database path expectations.

Success Criteria
How we know the feature works.

A clean build produces the image successfully, required PHP modules are available in the container runtime, and starting the container makes the application reachable at the configured HTTP port.

## Implementation Plan

1 add base PHP image and install system packages required for sqlite/gd build
2 enable and verify PHP extensions `pdo_sqlite` and `gd` in Dockerfile build steps
3 set container working directory and copy application files with minimal ignore-aware context
4 configure runtime command to serve `public/index.php` via PHP built-in server on container port
5 build image and run container to confirm HTTP reachability of the application entrypoint
6 run in-container module checks to confirm `pdo_sqlite` and `gd` are loaded

## Tests

**Test cases**

- Success: Docker image builds from project root without errors.
- Success: Container starts and HTTP root responds from the app entrypoint.
- Success: `pdo_sqlite` and `gd` are present in loaded PHP modules.
- Failure: Build fails when Dockerfile references a missing/invalid package or extension config.
- Failure: HTTP check fails when container port mapping/start command is wrong.
- Edge: Build with `--no-cache` still succeeds and runtime checks still pass.
- Edge: Starting a second container on same host port fails clearly (port collision expected).

**Execute tests**

```bash
set -euo pipefail

BASE=http://localhost:8080
IMAGE=camagru-php:test
CONTAINER=camagru-php-test

# clean previous test container if present
docker rm -f "$CONTAINER" >/dev/null 2>&1 || true

# success: image builds
docker build --no-cache -t "$IMAGE" .

# success: required PHP modules are loaded
docker run --rm "$IMAGE" php -m | rg '^pdo_sqlite$'
docker run --rm "$IMAGE" php -m | rg '^gd$'

# success: container starts and serves HTTP
docker run -d --name "$CONTAINER" -p 8080:8080 "$IMAGE"
sleep 2

# success: app entrypoint is reachable over HTTP
curl -i "$BASE/"

# failure check: wrong port should fail to connect
if curl -fsS http://localhost:18080/ >/dev/null; then
  echo "Unexpected success on wrong port" >&2
  exit 1
else
  echo "Expected failure on wrong port"
fi

# edge: port collision should fail for second container
if docker run --rm -d --name "${CONTAINER}-2" -p 8080:8080 "$IMAGE" >/dev/null 2>&1; then
  echo "Unexpected success on duplicate port binding" >&2
  docker rm -f "${CONTAINER}-2" >/dev/null 2>&1 || true
  exit 1
else
  echo "Expected failure on duplicate port binding"
fi

# cleanup
docker rm -f "$CONTAINER"
```
