# Feature: Comments persistence

## Goal

Provide a **durable persistence layer** for user comments on published gallery images: each comment is stored as a row in the existing `comments` table (`user_id`, `image_id`, `content`, `created_at`), matching `docs/feature_tree.md` (“Store comment with author id, image id, timestamp”) and [GitHub #29](https://github.com/tomjoy75/camagru_php/issues/29). This issue delivers **repository-level create (and any minimal helpers needed for integrity)** so later issues can add the **comment form** (#27), **input validation** (#28), and **listing** (#30) without redesigning storage.

## Behavior

- **Schema:** Rely on `database/schema.sql`: `comments` with `id`, `user_id`, `image_id`, `content`, `created_at` (default `CURRENT_TIMESTAMP`), and `FOREIGN KEY` to `users(id)` and `images(id)` with `ON DELETE CASCADE`. If the project uses a migration or init step, applying this schema (or equivalent) on fresh and existing dev DBs is part of making persistence real—**no new columns** unless the subject explicitly requires them later.
- **Repository:** Introduce a **`CommentRepository`** (or the same naming pattern as `LikeRepository`) that performs **only database access** for comments: at minimum an **`insert`** that persists one comment given **caller-supplied** `user_id`, `image_id`, and `content` (trimmed or raw per caller; **length and emptiness rules belong to #28**). **`insert` contract:** returns the new comment’s **`id` (positive int) on success**; returns **`null` on any failure** (including invalid references below). **No HTML** and **no session logic** inside the repository.
- **Referential integrity:** For **invalid `image_id`** (no row in `images`) **or invalid `user_id`** (no row in `users`), the repository must **not** insert a row, must **return `null`**, and must **not** leak exceptions to callers (expected failures are handled inside the repository). **Mechanism:** you may use **explicit existence checks** (e.g. prepared `SELECT` on `images` / `users`, such as `imageExists` / `userExists`) **and/or** SQLite **`FOREIGN KEY` enforcement** when enabled; **observable behavior must be the same** (no insert, `null`, no exception leakage). Invalid ids must **not** corrupt data or expose raw SQL errors to HTTP clients when a controller is wired later.
- **Read path:** Loading comments for display (**ordered list, usernames, timestamps**) is **[Comments] Comment listing per image** (#30). This spec may add **private repository methods** only if they are strictly required to verify persistence (e.g. `findById` for tests); avoid building the full public read API here unless it is the smallest way to prove inserts.
- **Observability:** After a successful insert, the row is visible in SQLite (e.g. `SELECT * FROM comments WHERE image_id = ?`) with correct `user_id`, `image_id`, `content`, and a sensible `created_at`. Existing **comment counts** on gallery detail (already aggregated in `ImageRepository` / `GalleryController`) should **increment** once a POST flow exists; until #27/#28 wire HTTP, verification may be **manual SQL** or a **temporary dev-only** call path removed before submission if your workflow forbids leaving it (see `docs/ARCHITECT.md` regarding dev endpoints).

## Constraints

- **MVC:** Persistence lives in **`src/repository/`**; business rules about *when* a comment may be created stay in a **service or controller** when HTTP is added (#27/#28). Views remain HTML-only.
- **PHP:** Standard library only; **prepared statements** for every query touching user or image ids and `content`.
- **Security:** Repository methods accept **already authenticated** `user_id` from trusted server-side context only—**never** take “acting user id” from raw POST for insert without the controller having validated the session. Escaping for display is **not** this issue’s job but stored text should be storable as plain UTF-8 text for later `htmlspecialchars` in views.
- **Integrity (observable):** Regardless of pre-checks vs FK enforcement, callers see the same **`insert` → id or `null`** contract; do not depend on SQLite defaults for FK without documenting how invalid references still yield `null`.
- **Consistency:** Match patterns used by `LikeRepository` (require `Database.php`, small focused methods, no business logic).

## Success Criteria

- **S1:** Calling the new **`insert`** with a valid existing `user_id` and `image_id` and non-empty `content` (per test setup) creates **exactly one** new `comments` row with matching fields and an auto-generated `id` and `created_at`.
- **S2:** Insert with an `image_id` that does **not** exist in `images` (or invalid `user_id`) does **not** leave orphan or partial rows; failure is handled safely (no uncaught exceptions in normal operation, no **500** in any wired HTTP path if one exists in the same delivery).
- **S3:** No duplicate or conflicting schema: `comments` matches `database/schema.sql` (or project-approved migration).
- **S4:** After this work, **[GitHub #30](https://github.com/tomjoy75/camagru_php/issues/30)** can list comments using the same table without migrating data again.

## Implementation Plan

1. confirm dev `database/camagru.db` has a `comments` table matching `database/schema.sql` (recreate or apply DDL if missing)
2. add `src/repository/CommentRepository.php` with `require_once Database.php` and an empty class matching `LikeRepository` style
3. add existence helpers via prepared `SELECT` on `images` and `users` (e.g. `imageExists` / `userExists`) so invalid `image_id` or `user_id` is detected before `INSERT` **or** map caught FK/constraint failures to `null` if you rely on enforced FKs instead—either way no insert and no leaked exceptions
4. implement `insert(int $userId, int $imageId, string $content): ?int`: **success** → positive new comment `id` (`lastInsertId()`); **failure** (invalid `image_id`, invalid `user_id`, failed `INSERT`, FK violation) → **`null`**; handle expected errors inside the repository
5. *(optional)* after PDO opens in `Database::getConnection()`, run `PRAGMA foreign_keys=ON` once per connection as extra enforcement alongside step 3—**not required** if step 3 alone satisfies the contract
6. run the runnable checks in `## Tests` (valid ids; unknown `image_id`; unknown `user_id`)

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

## Tests

**Test cases**

**Success**

- **S1:** `insert` with an existing `user_id`, existing `image_id`, and non-empty `content` returns a positive integer id; `comments` has a new row with matching `user_id`, `image_id`, `content`, and non-null `created_at`.
- **S2:** A second `insert` (same `image_id`, same or different `user_id` as allowed by schema) returns a different id and increments `COUNT(*)` on `comments` by 1.

**Failure**

- **F1:** `insert` with an `image_id` that has no row in `images` returns `null` (or documented failure) and `COUNT(*)` on `comments` is unchanged.
- **F2:** `insert` with a `user_id` that has no row in `users` returns `null` or fails without an uncaught exception; no invalid row remains in `comments`.

**Edge**

- **E1:** Non-positive `image_id` yields no insert (`imageExists` false or equivalent); `comments` unchanged.
- **E2:** `content` containing UTF-8, quotes, and newlines round-trips: stored value matches what was passed (or documented normalization only if #28 adds it later).

**Execute tests**

Prerequisite: `database/camagru.db` has at least one `users` row and one `images` row. Run from the **repository root**. This feature is **repository-only**; **`curl`** checks for comment **HTTP** belong in #27/#28 (`docs/WORKFLOW-addendum-web-server.md`).

**Test setup (ids)**

```bash
DB=database/camagru.db
export USER_ID=$(sqlite3 "$DB" 'SELECT id FROM users ORDER BY id LIMIT 1;')
export IMAGE_ID=$(sqlite3 "$DB" 'SELECT id FROM images ORDER BY id LIMIT 1;')
```

```bash
# S1: insert returns positive id; latest row matches
php -r '
require_once __DIR__ . "/src/db/Database.php";
require_once __DIR__ . "/src/repository/CommentRepository.php";
$r = new CommentRepository();
$id = $r->insert((int) getenv("USER_ID"), (int) getenv("IMAGE_ID"), "spec S1");
exit($id && $id > 0 ? 0 : 1);
'
sqlite3 "$DB" 'SELECT user_id, image_id, content FROM comments ORDER BY id DESC LIMIT 1;'

# S2: second insert increases COUNT(*)
C1=$(sqlite3 "$DB" 'SELECT COUNT(*) FROM comments;')
php -r '
require_once __DIR__ . "/src/db/Database.php";
require_once __DIR__ . "/src/repository/CommentRepository.php";
$r = new CommentRepository();
$id = $r->insert((int) getenv("USER_ID"), (int) getenv("IMAGE_ID"), "spec S2");
exit($id && $id > 0 ? 0 : 1);
'
C2=$(sqlite3 "$DB" 'SELECT COUNT(*) FROM comments;')
test "$C2" -gt "$C1" || exit 1

# F1: unknown image_id → null; count unchanged
C0=$(sqlite3 "$DB" 'SELECT COUNT(*) FROM comments;')
php -r '
require_once __DIR__ . "/src/db/Database.php";
require_once __DIR__ . "/src/repository/CommentRepository.php";
$r = new CommentRepository();
$id = $r->insert((int) getenv("USER_ID"), 2147483647, "no");
exit($id === null ? 0 : 1);
'
test "$(sqlite3 "$DB" 'SELECT COUNT(*) FROM comments;')" -eq "$C0" || exit 1

# F2: unknown user_id → null (or safe failure); count unchanged — pick USER_BAD not in DB
C0=$(sqlite3 "$DB" 'SELECT COUNT(*) FROM comments;')
export USER_BAD=2147483647
php -r '
require_once __DIR__ . "/src/db/Database.php";
require_once __DIR__ . "/src/repository/CommentRepository.php";
$r = new CommentRepository();
$id = $r->insert((int) getenv("USER_BAD"), (int) getenv("IMAGE_ID"), "no");
exit($id === null ? 0 : 1);
'
test "$(sqlite3 "$DB" 'SELECT COUNT(*) FROM comments;')" -eq "$C0" || exit 1

# E1: image_id 0 → null
php -r '
require_once __DIR__ . "/src/db/Database.php";
require_once __DIR__ . "/src/repository/CommentRepository.php";
$r = new CommentRepository();
$id = $r->insert((int) getenv("USER_ID"), 0, "no");
exit($id === null ? 0 : 1);
'

# E2: UTF-8 / newlines round-trip (inspect output)
php -r '
require_once __DIR__ . "/src/db/Database.php";
require_once __DIR__ . "/src/repository/CommentRepository.php";
$r = new CommentRepository();
$t = "Line1\nLine2 \"q\" éclair";
$id = $r->insert((int) getenv("USER_ID"), (int) getenv("IMAGE_ID"), $t);
exit($id ? 0 : 1);
'
sqlite3 "$DB" 'SELECT content FROM comments ORDER BY id DESC LIMIT 1;'
```
