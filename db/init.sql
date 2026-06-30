-- db/init.sql
-- Schema reconstructed from the PHP source of my_eshop.
-- Auto-imported by the MySQL container on FIRST start only (when the data volume is empty).
-- NOTE: the shopping cart is session-based ($_SESSION['cart']); there is NO cart table.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- users
--   Referenced by: register.php, login.php, orders.user_id (FK), view_orders JOIN
--   Admin is NOT stored in a column. login.php grants admin when
--   username = 'admin' OR id = 1.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  username   VARCHAR(50)   NOT NULL,
  email      VARCHAR(255)  NOT NULL,
  password   VARCHAR(255)  NOT NULL,            -- password_hash() output (bcrypt $2y$)
  role       ENUM('super_admin','seller','customer') NOT NULL DEFAULT 'customer',
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- products
--   Referenced by: index.php, product.php, cart.php, admin/add_product.php,
--   admin/manage_products.php.  Image filename stored in `image`
--   (the original product.php/cart.php used `image_url` — that was a bug,
--   now normalised to `image`).  manage_products orders by created_at.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name        VARCHAR(255)  NOT NULL,
  description TEXT          NOT NULL,
  price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  image       VARCHAR(255)  NOT NULL DEFAULT '',  -- filename inside my_eshop/uploads/
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- orders
--   Written by checkout.php (status defaults to 'Pending').
--   Updated/read by admin/view_orders.php (JOIN users, ORDER BY created_at).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED  NOT NULL,
  total_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  shipping_address TEXT          NOT NULL,
  status           VARCHAR(20)   NOT NULL DEFAULT 'Pending',  -- Pending/Processing/Shipped/Delivered/Cancelled
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orders_user_id (user_id),
  KEY idx_orders_created_at (created_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
      REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- order_items
--   One row per product line, written by checkout.php in a transaction.
--   price_at_purchase snapshots the unit price at order time.
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
-- wishlist
--   One row per (user, product) pair.  Session-based web pages and the REST
--   API both write here.  Cascade-deleted when either the user or product is removed.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wishlist (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wishlist (user_id, product_id),
  CONSTRAINT fk_wl_user    FOREIGN KEY (user_id)    REFERENCES users    (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_wl_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed a super_admin account so the /admin pages work immediately.
--   Login:    admin@example.com  (or username: admin)
--   Password: admin123           <-- CHANGE THIS in production
-- ---------------------------------------------------------------------------
INSERT INTO users (id, username, email, password, role) VALUES
  (1, 'admin', 'admin@example.com', '$2y$10$wyoBMAiihzJxR9tghOc7.uYWVrz/d4xu.tndujV8QXcz6Sn9QQWoW', 'super_admin')
ON DUPLICATE KEY UPDATE username = VALUES(username);
