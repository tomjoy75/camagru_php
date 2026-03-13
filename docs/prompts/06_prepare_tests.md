# Step 6: Prepare Tests

**Source:** WORKFLOW.md §9 – Test cases and Issue Creation (Generate Tests)

**Output:** A "## Tests" section appended to `docs/specs/<feature_name>.md`

---

## Prompt

```
Read docs/specs/<feature_name>.md.

Generate tests before implementing features.

Include:
- success cases
- failure cases
- edge cases

Keep the test case list concise.

After the test case list, automatically generate a bash code block containing curl commands that allow the developer to execute those tests.

The generated curl commands must:
- be minimal and runnable from the project root
- use multipart form data when files are required (e.g. -F "field=@path/to/file")
- use realistic example values (emails, usernames, file paths)
- define BASE=http://localhost:8080 for example once at the beginning of the bash blocks
- include a short comment before each curl command describing the test

If the feature requires authentication:
- generate a "Test Setup (authentication)" bash block first
- remove any previous cookie file (rm -f cookies.txt)
- include /register and /login requests
- store cookies using -c cookies.txt
- subsequent requests must reuse the session using -b cookies.txt

The output structure must be:

**Test cases**
(list: success / failure / edge cases)

**Test Setup (authentication)** [only if the feature requires auth]
```bash
# curl commands for register + login, -c cookies.txt
```

**Execute tests**
```bash
# curl commands
```

Keep the output concise. Do not modify other sections of the spec file.

Append the result in the same file (docs/specs/<feature_name>.md) under a new section titled "## Tests".
```

---

## Optional: Generate GitHub issue

```
Read docs/specs/<feature_name>.md.

Generate a GitHub issue body for this feature.

Include:
- feature description
- implementation plan as checkboxes
- test cases as checkboxes
```
