-- Add role column to user table
ALTER TABLE user ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'visitor';

-- Set the first user (usually the owner) as admin
UPDATE user SET role = 'admin' WHERE id = 1;

-- Optional: ensure future users created via registration (if enabled) are visitors by default
-- (Already handled by DEFAULT 'visitor')
