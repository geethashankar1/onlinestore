-- db/migrations/005_cart_items.sql
--
-- Give the cart a home in the database.
--
-- Until now the website kept the cart in $_SESSION and the mobile app kept it
-- in React state, so the same account had two unrelated carts: adding a model
-- on the phone and then signing in on the web showed an empty cart, and a
-- browser session expiring dropped the cart entirely. One row per
-- (user, product) makes the cart a property of the account, which is what a
-- customer already assumes it is.
--
-- Guests still use $_SESSION — there is no account to attach rows to. That
-- cart is merged into these rows the moment they log in or register.
--
-- Safe to re-run.

CREATE TABLE IF NOT EXISTS cart_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity   INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- One line per product: adding the same model twice bumps the quantity
  -- rather than growing a second row.
  UNIQUE KEY uq_cart_user_product (user_id, product_id),
  KEY idx_cart_user (user_id),
  CONSTRAINT fk_cart_user    FOREIGN KEY (user_id)    REFERENCES users    (id) ON DELETE CASCADE ON UPDATE CASCADE,
  -- A deleted product leaves nobody's cart holding a dangling line.
  CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
