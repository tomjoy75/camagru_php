-- SQLite schema for Camagru (minimal)
--
-- Existing databases created before email verification: add columns once, e.g.:
--   ALTER TABLE users ADD COLUMN email_verified INTEGER NOT NULL DEFAULT 0;
--   ALTER TABLE users ADD COLUMN confirmation_token TEXT;
--
-- Password reset request (#53): add once to existing DBs, e.g.:
--   ALTER TABLE users ADD COLUMN password_reset_token TEXT;
--   ALTER TABLE users ADD COLUMN password_reset_expires_at TEXT;
-- Compose metadata on saved images (#20): add once to existing DBs, e.g.:
--   ALTER TABLE images ADD COLUMN last_sticker_filename TEXT;
--   ALTER TABLE images ADD COLUMN last_compose_x INTEGER;
--   ALTER TABLE images ADD COLUMN last_compose_y INTEGER;
--   ALTER TABLE images ADD COLUMN last_compose_scale REAL;
--   ALTER TABLE images ADD COLUMN last_compose_angle_deg REAL;

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    notifications_enabled INTEGER NOT NULL DEFAULT 1,
    email_verified INTEGER NOT NULL DEFAULT 0,
    confirmation_token TEXT,
    password_reset_token TEXT,
    password_reset_expires_at TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    image_path VARCHAR(512) NOT NULL,
    last_sticker_filename TEXT,
    last_compose_x INTEGER,
    last_compose_y INTEGER,
    last_compose_scale REAL,
    last_compose_angle_deg REAL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    image_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE
);

CREATE TABLE likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    image_id INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, image_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE
);
