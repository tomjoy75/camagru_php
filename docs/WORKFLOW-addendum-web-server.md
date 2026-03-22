# Workflow addendum: web server, MVC, HTTP

Use with **`docs/WORKFLOW.md`** when the project is a **server-rendered or API web app** (e.g. PHP MVC, similar stacks). The main workflow stays stack-agnostic; this file holds **optional** prompts, examples, and test conventions.

------------------------------------------------------------------------

## Architecture prompt supplement (extends WORKFLOW §3)

If you use **MVC-style** layering, you can extend the generic architecture prompt with:

- application layers (e.g. HTTP boundary, application services, persistence)
- controllers (or handlers) and routing
- services (domain/application logic)
- repositories or data access
- database or storage shape

### Example structure (MVC monolith)

```
# Architecture

Pattern
MVC Monolith

Controllers
- AuthController
- EditorController
- GalleryController

Services
- AuthService
- ImageService
- NotificationService

Repositories
- UserRepository
- ImageRepository
- CommentRepository

Database tables
- users
- images
- comments
- likes
```

------------------------------------------------------------------------

## Feature selection (extends WORKFLOW §5)

For web MVC, item 3 in the selection output can be phrased as:

- **controllers / services / repositories** (or handlers and stores) **touched**

------------------------------------------------------------------------

## Implementation plan examples (extends WORKFLOW §7)

HTTP-oriented step list (when features are route-driven):

1. create route  
2. validate input  
3. call service  
4. store data  
5. return response  

------------------------------------------------------------------------

## Spec-driven gate (extends WORKFLOW §7.5)

When coding a web app, “application code” typically includes **routes, handlers, templates/views, services, repositories**. Do not widen scope with **extra endpoints** or dependencies without explicit approval.

Align layering with **`docs/architecture.md`** and the **MVC-oriented Core Principles** in this addendum.

------------------------------------------------------------------------

## Core Principles (MVC web profile)

Use alongside WORKFLOW **Core Principles** when the project follows MVC:

- Controllers (or HTTP handlers) stay thin  
- Business logic lives in services (or domain layer)  
- Repositories (or equivalent) handle persistence  
- Views/templates render output only; they do not access the database directly  

------------------------------------------------------------------------

## Test plan — HTTP with curl (extends WORKFLOW §9)

When the feature is verified via **HTTP**, append runnable **`curl`** blocks to the feature spec under `## Tests`.

### AI prompt extension

After the test case list, generate a bash code block with `curl` commands that exercise those cases.

The generated curl commands should:

- be minimal and runnable from the project root  
- use multipart form data when files are required (e.g. `-F "field=@path/to/file"`)  
- use realistic example values (emails, usernames, file paths)  
- define `BASE=http://localhost:8080` (or the project’s base URL) once at the start of the bash blocks  
- include a short comment before each curl describing the test  

If the feature requires **session authentication**:

- add a **Test Setup (authentication)** bash block first  
- `rm -f cookies.txt` before setup  
- include register/login (or equivalent) requests  
- store cookies with `-c cookies.txt`  
- subsequent requests use `-b cookies.txt`  

### Output shape in the spec

**Test cases**  
(list: success / failure / edge)

**Test Setup (authentication)** [only if needed]

```bash
# register + login, -c cookies.txt
```

**Execute tests**

```bash
# curl commands
```

Keep it concise. Append under `## Tests` without rewriting other spec sections.

------------------------------------------------------------------------

## Commits (optional)

Example conventional commit for HTTP features:

`feat(editor): add upload endpoint`
