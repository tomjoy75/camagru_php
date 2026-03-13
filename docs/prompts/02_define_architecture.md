# Step 2: Define Architecture

**Source:** WORKFLOW.md §3 – Architecture Definition

**Output:** `docs/architecture.md` (or `docs/ARCHITECT.md` if that is the project convention)

---

## Prompt

```
Read docs/product_spec.md.

Propose a software architecture including:

- application layers
- controllers
- services
- repositories
- database structure
```

## Example structure

- **Pattern** – e.g. MVC Monolith
- **Controllers** – AuthController, EditorController, GalleryController
- **Services** – AuthService, ImageService, NotificationService
- **Repositories** – UserRepository, ImageRepository, CommentRepository
- **Database Tables** – users, images, comments, likes
