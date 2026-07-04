-- Migration 002: Multi-tenant stores foundation
-- Run once against the live database.
-- Adds: stores table, store_id to products/orders/wishlist.

SET NAMES utf8mb4;

-- ── 1. STORES TABLE ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS stores (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  seller_id     INT UNSIGNED  NOT NULL,
  name          VARCHAR(255)  NOT NULL,
  slug          VARCHAR(100)  NOT NULL,           -- unique URL key: /shop/{slug}
  logo          VARCHAR(255)  DEFAULT NULL,        -- filename in uploads/stores/
  banner        VARCHAR(255)  DEFAULT NULL,
  description   TEXT          DEFAULT NULL,
  primary_color VARCHAR(7)    NOT NULL DEFAULT '#1a1a1a',
  status        ENUM('pending','active','suspended') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stores_slug     (slug),
  UNIQUE KEY uq_stores_seller   (seller_id),
  CONSTRAINT fk_stores_seller   FOREIGN KEY (seller_id)
      REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. ADD store_id TO products ───────────────────────────────────
-- NULL = platform-wide product (super_admin); non-null = seller's product.
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS store_id INT UNSIGNED NULL AFTER id,
  ADD KEY IF NOT EXISTS idx_products_store (store_id);

-- Add FK only if it doesn't already exist
SET @exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'products'
    AND CONSTRAINT_NAME = 'fk_products_store'
);
SET @sql = IF(@exists = 0,
  'ALTER TABLE products ADD CONSTRAINT fk_products_store
   FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. ADD store_id TO orders ─────────────────────────────────────
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS store_id INT UNSIGNED NULL AFTER id,
  ADD KEY IF NOT EXISTS idx_orders_store (store_id);

SET @exists2 = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'orders'
    AND CONSTRAINT_NAME = 'fk_orders_store'
);
SET @sql2 = IF(@exists2 = 0,
  'ALTER TABLE orders ADD CONSTRAINT fk_orders_store
   FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- ── 4. ADD store_id TO wishlist ───────────────────────────────────
ALTER TABLE wishlist
  ADD COLUMN IF NOT EXISTS store_id INT UNSIGNED NULL AFTER id,
  ADD KEY IF NOT EXISTS idx_wishlist_store (store_id);
