-- Migration 003: collapse the multi-tenant marketplace to a single-vendor shop
--
-- Removes the stores layer: one owner (super_admin) sells, everyone else is a
-- customer. The marketplace version is preserved on the 'multi-tenant' branch.
--
-- Run ONCE against a database created by 002. Written without stored procedures
-- so it also runs on TiDB.
--
-- ORDER MATTERS: foreign keys must go before the columns they sit on, and the
-- columns before the table they reference.

SET NAMES utf8mb4;

-- ── 1. Any remaining sellers become customers ────────────────────
-- Do this first: the ENUM below no longer has a 'seller' value to hold them.
UPDATE users SET role = 'customer' WHERE role = 'seller';

-- ── 2. Drop the foreign keys pointing at stores ──────────────────
ALTER TABLE products DROP FOREIGN KEY fk_products_store;
ALTER TABLE orders   DROP FOREIGN KEY fk_orders_store;

-- ── 3. Drop the store_id columns and their indexes ───────────────
ALTER TABLE products DROP INDEX idx_products_store, DROP COLUMN store_id;
ALTER TABLE orders   DROP INDEX idx_orders_store,   DROP COLUMN store_id;
ALTER TABLE wishlist DROP INDEX idx_wishlist_store, DROP COLUMN store_id;

-- ── 4. Drop the stores table ─────────────────────────────────────
DROP TABLE IF EXISTS stores;

-- ── 5. Narrow the role enum ──────────────────────────────────────
ALTER TABLE users
  MODIFY COLUMN role ENUM('super_admin','customer') NOT NULL DEFAULT 'customer';
