-- db/migrations/001_add_role_to_users.sql
-- Adds a real `role` column to `users`, replacing the hardcoded
-- username==='admin' OR id===1 admin check in login.php.
-- Idempotent: safe to re-run against a live database.

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'
);
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN role ENUM(''super_admin'',''seller'',''customer'') NOT NULL DEFAULT ''customer'' AFTER password',
  'SELECT ''role column already exists'' AS notice'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The existing seeded admin (id=1) becomes the first super_admin.
UPDATE users SET role = 'super_admin' WHERE id = 1 AND role <> 'super_admin';
