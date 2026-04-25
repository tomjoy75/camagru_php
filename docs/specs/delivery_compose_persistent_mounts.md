# Feature: `Delivery Compose persistent mounts`

Goal  
Add persistent storage mounts to the Docker Compose app service so key runtime data survives container restarts in a predictable way.

Behavior  
Update compose runtime storage so the database path and upload path are mounted from the host into the app container.  
Starting, stopping, and restarting the compose service should preserve files stored in mounted paths.

Constraints  
Keep this feature limited to storage mounts only for one implementation cycle.  
Do not implement first-run DB bootstrap behavior in this spec (tracked separately in issue #70 follow-up slice).  
Do not add unrelated compose services or README documentation changes in this cycle.

Success Criteria  
Compose configuration includes mounts for database storage and `public/uploads` (and `public/tmp` only if runtime requires it).  
After `docker compose down` and `docker compose up`, data written in mounted paths remains available.  
No DB schema-init strategy changes are introduced in this slice.

## Implementation Plan

1 add compose volume mappings for database storage and `public/uploads`
2 add `public/tmp` mapping only if runtime behavior requires a writable temp path
3 start compose service and verify container boots with mounted paths
4 write a test file in each mounted path and verify files exist from host side
5 run `docker compose down` then `docker compose up` and verify mounted files persist

## Tests

**Test cases**

- Success: compose starts with volume mappings for database path and `public/uploads`.
- Success: test files written in mounted paths are visible from host filesystem.
- Failure: invalid/missing host mount path causes compose startup or runtime write failure.
- Failure: mount path typo prevents expected persistence after restart.
- Edge: repeated `docker compose up -d` keeps service healthy with mounts attached.
- Edge: `docker compose down` then `up -d` preserves previously written mounted files.

**Execute tests**

```bash
set -euo pipefail

DB_PROBE="database/.mount_probe_db"
UPLOAD_PROBE="public/uploads/.mount_probe_upload"
TMP_PROBE="public/tmp/.mount_probe_tmp"

# clean start
docker compose down --remove-orphans || true

# start service
docker compose up -d --build

# success: create probe files from host in mounted paths
mkdir -p database public/uploads public/tmp
echo "db-probe-$(date +%s)" > "$DB_PROBE"
echo "upload-probe-$(date +%s)" > "$UPLOAD_PROBE"
echo "tmp-probe-$(date +%s)" > "$TMP_PROBE"

# success: verify probes exist from host perspective
test -f "$DB_PROBE"
test -f "$UPLOAD_PROBE"
test -f "$TMP_PROBE"

# edge: repeated up remains stable
docker compose up -d

# edge: restart cycle preserves mounted files
docker compose down
docker compose up -d
test -f "$DB_PROBE"
test -f "$UPLOAD_PROBE"
test -f "$TMP_PROBE"

# cleanup
docker compose down --remove-orphans
```
