# users
id
email UNIQUE
username
password_hash
notifications_enabled = true
created_at

# images
id
user_id
image_path
created_at

# comments
id
user_id
image_id
content
created_at

# likes
id
user_id
image_id
created_at
UNIQUE(user_id, image_id)