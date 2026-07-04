-- Migration 002: Multi-tenant stores foundation
-- Run ONCE. Uses stored procedures for safe idempotent column/index adds.

SET NAMES utf8mb4;

-- ── 1. STORES TABLE ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS stores (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  seller_id     INT UNSIGNED  NOT NULL,
  name          VARCHAR(255)  NOT NULL,
  slug          VARCHAR(100)  NOT NULL,
  logo          VARCHAR(255)  DEFAULT NULL,
  banner        VARCHAR(255)  DEFAULT NULL,
  description   TEXT          DEFAULT NULL,
  primary_color VARCHAR(7)    NOT NULL DEFAULT '#1a1a1a',
  status        ENUM('pending','active','suspended') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stores_slug   (slug),
  UNIQUE KEY uq_stores_seller (seller_id),
  CONSTRAINT fk_stores_seller FOREIGN KEY (seller_id)
      REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. products: add store_id ────────────────────────────────────
DROP PROCEDURE IF EXISTS _mig_add_store_id;
DELIMITER //
CREATE PROCEDURE _mig_add_store_id()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='store_id'
  ) THEN
    ALTER TABLE products ADD COLUMN store_id INT UNSIGNED NULL AFTER id;
    ALTER TABLE products ADD KEY idx_products_store (store_id);
    ALTER TABLE products ADD CONSTRAINT fk_products_store
      FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE ON UPDATE CASCADE;
  END IF;
END//
DELIMITER ;
CALL _mig_add_store_id();
DROP PROCEDURE IF EXISTS _mig_add_store_id;

-- ── 3. orders: add store_id ──────────────────────────────────────
DROP PROCEDURE IF EXISTS _mig_orders_store;
DELIMITER //
CREATE PROCEDURE _mig_orders_store()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='store_id'
  ) THEN
    ALTER TABLE orders ADD COLUMN store_id INT UNSIGNED NULL AFTER id;
    ALTER TABLE orders ADD KEY idx_orders_store (store_id);
    ALTER TABLE orders ADD CONSTRAINT fk_orders_store
      FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END//
DELIMITER ;
CALL _mig_orders_store();
DROP PROCEDURE IF EXISTS _mig_orders_store;

-- ── 4. wishlist: add store_id ─────────────────────────────────────
DROP PROCEDURE IF EXISTS _mig_wishlist_store;
DELIMITER //
CREATE PROCEDURE _mig_wishlist_store()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wishlist' AND COLUMN_NAME='store_id'
  ) THEN
    ALTER TABLE wishlist ADD COLUMN store_id INT UNSIGNED NULL AFTER id;
    ALTER TABLE wishlist ADD KEY idx_wishlist_store (store_id);
  END IF;
END//
DELIMITER ;
CALL _mig_wishlist_store();
DROP PROCEDURE IF EXISTS _mig_wishlist_store;
