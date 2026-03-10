# Camagru – Feature Tree

**Product: Camagru**

---

## Module: Authentication

### Feature: User registration

- **Subfeature: Registration form (UI)**  
  Email, username, password, password confirmation fields; basic responsive layout and form styling.
- **Subfeature: Input validation (server-side)**  
  Email required and valid format; email uniqueness check (`UserRepository::findByEmail`); username required, length constraints, allowed characters; password minimum length; password confirmation matching.
- **Subfeature: Account creation**  
  Password hashing with `password_hash`; user persisted in DB (`UserRepository::createUser`).
- **Subfeature: Error feedback**  
  Field-level error messages (email, username, password, confirm_password); global form error (e.g. DB failure).
- **Subfeature: Email confirmation** *(from subject, not yet implemented)*  
  Generate unique confirmation token; send confirmation link by email; confirmation endpoint to activate account.

### Feature: Login

- **Subfeature: Login form (UI)**  
  Email + password inputs; error display area for invalid credentials.
- **Subfeature: Credential validation**  
  Email required; user lookup by email; password verification with `password_verify`.
- **Subfeature: Session management**  
  Store authenticated user id in session on success; central session start in `public/index.php`.
- **Subfeature: Error handling**  
  Generic “invalid email or password” message.

### Feature: Logout

- **Subfeature: One-click logout**  
  Logout link in header when authenticated; session destruction and redirect to login.

### Feature: Account management *(from subject, not yet implemented)*

- **Subfeature: Profile editing**  
  Change username; change email (with re-validation and uniqueness); change password (with validation).
- **Subfeature: Password reset**  
  Request reset link via email; token-based password reset flow.

---

## Module: Editor

### Feature: Access control

- **Subfeature: Auth-only access**  
  Editor page redirects unauthenticated users to `/login`.

### Feature: Editing workspace UI

- **Subfeature: Webcam preview**  
  Main video area to show camera feed (currently placeholder).
- **Subfeature: Sticker selection**  
  Sticker list panel with PNG overlays; sticker discovery via filesystem (`StickerService` / `editor.php`); visual thumbnails with hover titles and alt text.
- **Subfeature: Capture / upload controls**  
  Capture button (to trigger snapshot from webcam); upload image control (file input accepting image/*).

### Feature: Image composition *(from subject, to be implemented)*

- **Subfeature: Server-side image processing**  
  Receive base image (webcam capture or upload); receive chosen sticker(s) and position data; compose final image on server (GD/ImageMagick via PHP standard library).
- **Subfeature: Persist edited images**  
  Store resulting image file; save metadata: owner user id, created_at, sticker used, etc.

### Feature: User image management *(from subject, to be implemented)*

- **Subfeature: Personal gallery/sidebar**  
  Show thumbnails of user’s previous images (replacing placeholders).
- **Subfeature: Deletion**  
  Allow user to delete their own images only.

---

## Module: Gallery

### Feature: Public gallery *(from subject, to be implemented)*

- **Subfeature: Listing**  
  Display all edited images from all users; order by creation date (newest first).
- **Subfeature: Pagination**  
  At least 5 images per page; navigation between pages.
- **Subfeature: Image details**  
  Show image, author, creation date; show like count and comments summary.

### Feature: Gallery filtering & navigation *(optional future)*

- **Subfeature: Filter by user**  
  View all images from a particular user.
- **Subfeature: Filter by popularity or recency**  
  Sort by likes or comments.

---

## Module: Comments

### Feature: Comment creation *(from subject, to be implemented)*

- **Subfeature: Comment form**  
  Available only to logged-in users; textarea + submit for each image.
- **Subfeature: Input validation**  
  Non-empty comments; length limit / sanitization.
- **Subfeature: Persistence**  
  Store comment with author id, image id, timestamp.

### Feature: Comment display

- **Subfeature: Comment listing per image**  
  Ordered by creation date; show commenter username and timestamp.

### Feature: Comment moderation *(optional)*

- **Subfeature: Deletion / editing**  
  Allow author or admin to delete/edit their comments.

---

## Module: Likes

### Feature: Like interaction *(from subject, to be implemented)*

- **Subfeature: Like/unlike actions**  
  Only logged-in users can like images; toggling like state per user per image.
- **Subfeature: Like count**  
  Aggregate likes per image; show like count in gallery and/or image view.

### Feature: UI feedback

- **Subfeature: Live like state**  
  Visual indication of whether the current user liked an image; optimistic updates / AJAX (bonus: “AJAXify exchanges with the server”).

---

## Module: Notifications

### Feature: Comment notifications *(from subject, to be implemented)*

- **Subfeature: Preference setting**  
  Boolean `notifications_enabled` per user (already in schema); defaults to true on registration; option in user settings to disable notifications.
- **Subfeature: Triggering notifications**  
  When a new comment is added to an image; check if image author’s notifications are enabled.
- **Subfeature: Email sending**  
  Compose email with link to commented image; send email to image author.

### Feature: Other notifications *(optional/bonus)*

- **Subfeature: In-app notifications**  
  Show unread notification count; list of recent notifications (likes, comments, etc.).
