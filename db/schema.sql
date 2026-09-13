-- db/schema.sql
-- Canonical schema for a FRESH database.
--
-- Single-vendor shop: one owner (super_admin) sells, everyone else is a
-- customer. Migration 003 removed the multi-tenant stores layer; the
-- marketplace version lives on the 'multi-tenant' branch.
--
-- Why this exists separately from db/init.sql + db/migrations/:
-- Migration 002 wraps its column-adds in stored procedures, which TiDB does not
-- support; this file declares the final shape directly and runs anywhere.
--
-- Use this when provisioning a new environment. Keep using the numbered
-- migrations to evolve a database that already holds data.
--
-- Tables are ordered so every foreign-key target exists before it is referenced.
-- The cart is session-based ($_SESSION['cart']); there is deliberately no cart table.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- users — roles live here (migration 001 added the column)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  username   VARCHAR(50)   NOT NULL,
  email      VARCHAR(255)  NOT NULL,
  password   VARCHAR(255)  NOT NULL,            -- password_hash() output (bcrypt $2y$)
  role       ENUM('super_admin','customer') NOT NULL DEFAULT 'customer',
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- products — `image` holds either an uploads/ filename or a full Cloudinary URL
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name         VARCHAR(255)  NOT NULL,
  brand        VARCHAR(80)   NOT NULL DEFAULT '',   -- car marque: Porsche, Nissan
  manufacturer VARCHAR(80)   NOT NULL DEFAULT '',   -- model maker: Hot Wheels, Kyosho
  scale        VARCHAR(20)   NOT NULL DEFAULT '',   -- 1:18, 1:64, …
  description  TEXT          NOT NULL,
  price        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  image        VARCHAR(255)  NOT NULL DEFAULT '',
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_created_at (created_at),
  KEY idx_products_brand (brand),
  KEY idx_products_manufacturer (manufacturer),
  KEY idx_products_scale (scale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- orders
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED  NOT NULL,
  total_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  shipping_address TEXT          NOT NULL,
  status           VARCHAR(20)   NOT NULL DEFAULT 'Pending',
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orders_user_id (user_id),
  KEY idx_orders_created_at (created_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
      REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- order_items — price_at_purchase snapshots the unit price at order time
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  order_id          INT UNSIGNED  NOT NULL,
  product_id        INT UNSIGNED  NOT NULL,
  quantity          INT           NOT NULL DEFAULT 1,
  price_at_purchase DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_oi_order_id (order_id),
  KEY idx_oi_product_id (product_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id)
      REFERENCES orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id)
      REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- wishlist — one row per (user, product)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wishlist (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wishlist (user_id, product_id),
  CONSTRAINT fk_wl_user    FOREIGN KEY (user_id)    REFERENCES users    (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_wl_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed a super_admin so /admin works on a fresh install.
--   Login: admin@example.com (or username: admin)
--   Password: admin123  <-- LOCAL DEVELOPMENT ONLY. Change it immediately on
--   any deployment; this hash is public in the repository.
-- ---------------------------------------------------------------------------
INSERT INTO users (id, username, email, password, role) VALUES
  (1, 'admin', 'admin@example.com', '$2y$10$wyoBMAiihzJxR9tghOc7.uYWVrz/d4xu.tndujV8QXcz6Sn9QQWoW', 'super_admin')
ON DUPLICATE KEY UPDATE username = VALUES(username);
