# Feature: `editor_disable_apply_sticker_when_no_selection`

Goal  
Prevent invalid compose attempts by disabling the `Apply sticker` action until a sticker is selected.

Behavior  
On editor load, `Apply sticker` is disabled when the compose sticker value is empty.  
When the user selects a valid sticker, `Apply sticker` becomes enabled.  
If the sticker selection is cleared, `Apply sticker` returns to disabled.  
The feature reuses the current hidden compose sticker input and existing sticker-pick flow.  
No backend route, controller, or service behavior changes.

Constraints  
Frontend-only change in the editor UI flow.  
Keep current server-side compose validation unchanged as the source of truth.  
Do not redesign sticker placement, save flow, capture flow, or upload flow.  
No new dependencies or framework changes.

Success Criteria  
`Apply sticker` is disabled whenever compose sticker value is empty.  
Selecting a sticker enables `Apply sticker` without requiring page reload.  
User cannot trigger the common empty-sticker compose request from normal UI interaction.  
Existing compose/save/capture/upload behavior remains unchanged outside this guardrail.

## Implementation Plan

1 compute a view-level boolean for compose sticker presence from existing hidden compose sticker value
2 render `Apply sticker` with `disabled` on first paint when compose sticker is not selected
3 wire client-side toggle logic to enable/disable `Apply sticker` on compose sticker selection changes
4 ensure sticker clear/reset paths also force `Apply sticker` back to disabled
5 verify no behavior change in backend compose validation and non-compose editor actions

## Tests

**Test cases**

- **Success**
  - **S1:** Authenticated `GET /editor` in `BASE_READY` with the compose form rendered and **no** compose sticker in the hidden field (`#editor-compose-sticker` empty) renders `#editor-compose-submit` as `disabled` on first paint.
  - **S2:** In browser, selecting a valid sticker enables `Apply sticker` immediately (no reload).
- **Failure**
  - **F1:** In browser, with no sticker selected, `Apply sticker` cannot be triggered from normal UI interaction.
  - **F2:** If sticker selection is cleared (or reset returns to empty compose sticker), `Apply sticker` returns to disabled.
- **Edge**
  - **E1:** Repeated sticker select/unselect cycles keep `Apply sticker` state accurate with no stale enabled state.
  - **E2:** Non-compose actions (capture/upload/save/reset) keep existing behavior unchanged.

**Test Setup (authentication)**

```bash
rm -f cookies.txt
BASE=http://127.0.0.1:8080
STAMP=$(date +%s)
EMAIL="editor69_${STAMP}@example.com"
USER="editor69_${STAMP}"
PASS='Testpass1!'

curl -sS -o /dev/null -w "register %{http_code}\n" -X POST "$BASE/register" \
  -d "email=$EMAIL" -d "username=$USER" -d "password=$PASS" -d "confirm_password=$PASS"
sqlite3 "${DATABASE_PATH:-database/camagru.db}" "UPDATE users SET email_verified = 1 WHERE email='$EMAIL';" 2>/dev/null || true
curl -sS -o /dev/null -w "login %{http_code}\n" -c cookies.txt -X POST "$BASE/login" \
  -d "username=$USER" -d "password=$PASS"
```

**Execute tests**

Run from the **repository root** so sticker paths resolve. The PHP built-in server must be up (`php -S 127.0.0.1:8080 public/index.php`).

```bash
BASE=${BASE:-http://127.0.0.1:8080}
REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
TEST_IMG="$REPO_ROOT/public/stickers/hat.png"
TEST_IMG2="$REPO_ROOT/public/stickers/glasses.png"

# Clean workspace
curl -sS -o /dev/null -b cookies.txt -c cookies.txt -X POST "$BASE/editor/reset"

# Establish BASE_READY: first upload requires a sticker when workspace is empty
curl -sS -o /dev/null -w "upload1 %{http_code}\n" -b cookies.txt -c cookies.txt -X POST "$BASE/editor/upload" \
  -F "sticker=hat.png" \
  -F "base_image=@${TEST_IMG}"

# Clear pending/compose sticker while keeping a base image (replacement upload, empty sticker clears session pending)
curl -sS -o /dev/null -w "upload2 %{http_code}\n" -b cookies.txt -c cookies.txt -X POST "$BASE/editor/upload" \
  -F "sticker=" \
  -F "base_image=@${TEST_IMG2}"

# S1 — compose form only in workspace states; empty #editor-compose-sticker + disabled #editor-compose-submit (newline-stripped HTML so multiline <button> still matches)
curl -sS -b cookies.txt "$BASE/editor" -o /tmp/editor69_s1.html
if grep -q 'id="editor-compose-submit"' /tmp/editor69_s1.html; then
  S1_FLAT=$(tr -d '\n' </tmp/editor69_s1.html)
  if printf '%s' "$S1_FLAT" | grep -q 'id="editor-compose-sticker"[^>]*value=""' \
    && printf '%s' "$S1_FLAT" | grep -q 'id="editor-compose-submit"[^>]*disabled'; then
    echo "S1 OK: empty #editor-compose-sticker, #editor-compose-submit disabled (BASE_READY)"
  else
    echo "S1 CHECK: expected empty #editor-compose-sticker and disabled on #editor-compose-submit opening tag"
  fi
else
  echo "S1 skip: compose form missing (workspace or stickers unavailable?)"
fi
```

**Manual / browser**

1. **S2 / F1:** Reach `BASE_READY` with the compose UI visible and **no** sticker chosen for placement (e.g. load a base, then replace the base via upload in a way that clears the pending sticker if your flow requires it). Confirm `Apply sticker` (`#editor-compose-submit`) is disabled, then pick a sticker and confirm it enables immediately.
2. **F2 / E1:** Clear sticker selection (or reset state that empties compose sticker), verify `Apply sticker` disables again; repeat select/clear multiple times and confirm state stays consistent.
3. **E2:** Run one normal compose/save cycle plus capture/upload/reset actions and verify no regression outside this guard.
