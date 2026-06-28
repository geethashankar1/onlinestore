# onlinestore (my_eshop)

A small PHP + MySQL e-commerce demo: product catalogue, session cart, checkout,
user auth, and an admin panel (add/manage products, view/update orders).
Runs fully in Docker — no local PHP or MySQL install needed.

## Stack
- PHP 8.3 + Apache (`mysqli`, prepared statements, `password_hash`)
- MySQL 8.0
- No framework, no build step

## Prerequisites
- Docker Desktop, or Docker Engine + Compose v2 (`docker compose version`)

## Run
```bash
docker compose up --build
```
First start imports `db/init.sql` automatically and seeds an admin user.

- Storefront: http://localhost:8080
- Admin panel: http://localhost:8080/admin/add_product.php

Stop with `Ctrl+C`, then `docker compose down`.
To wipe the database and re-seed from scratch: `docker compose down -v` (deletes the `db_data` volume).

## Login
| Account | Email | Password |
|---|---|---|
| Admin | `admin@example.com` (username `admin`) | `admin123` |

Change the password after first login. There is no "admin" column — `login.php`
grants admin rights when the username is `admin` **or** the user id is `1`.
To promote any other user, set their `username` to `admin` or make them user id 1.

## Database
Tables (cart is **session-based**, so there is no cart table):

| Table | Purpose | Key relations |
|---|---|---|
| `users` | accounts + bcrypt password | — |
| `products` | catalogue, image filename in `image` | — |
| `orders` | one per checkout | `user_id` → `users.id` |
| `order_items` | line items per order | `order_id` → `orders.id`, `product_id` → `products.id` |

Full DDL: `db/init.sql`.

## Configuration
DB settings come from environment variables (see `docker-compose.yml`). Defaults
work out of the box. To override, copy `.env.example` to `.env` and edit. `.env`
is git-ignored.

Inside Docker the DB host is the service name `db`, not `localhost` —
`config/db.php` reads `DB_HOST` accordingly.

## Project structure
```
onlinestore/
├── my_eshop/            # PHP app (Apache docroot)
│   ├── admin/           # add_product, manage_products, view_orders
│   ├── config/db.php    # env-driven mysqli connection
│   ├── css/  js/
│   ├── uploads/         # product images (.htaccess blocks PHP execution)
│   ├── index.php cart.php checkout.php product.php
│   └── login.php register.php logout.php
├── db/init.sql          # schema + seed admin
├── Dockerfile
├── docker-compose.yml
├── .env.example
└── README.md
```

## Changes made vs. the original upload
1. **Reconstructed the database** — the upload shipped no SQL; `db/init.sql` now
   creates all four tables with primary keys, foreign keys, and indexes.
2. **Fixed an `image` / `image_url` column bug** — `product.php` and `cart.php`
   queried a non-existent `image_url` column (the rest of the code uses `image`).
   Normalised to `image`; without this, product pages and the cart image render
   would have thrown "Unknown column".
3. **Renamed `uploads/.htaccess.txt` → `.htaccess`** — as `.txt` the
   "deny PHP execution" rule was inactive, so an uploaded `.php` could run.
   With `AllowOverride All` in Apache it is now enforced.
4. **Made `config/db.php` environment-driven** for Docker.
5. Added a placeholder image so missing product images don't 404.

## Known issues / not fixed (by design or out of scope)
- **Deleting a product that appears in an order is blocked** by the
  `order_items.product_id` foreign key (`ON DELETE RESTRICT`). This is intentional
  — it preserves order history. The admin delete will show an error in that case.
- **`index.php` search uses string interpolation** with `real_escape_string`
  rather than a prepared statement. It is escaped, but converting it to a bound
  parameter is the correct hardening step.
- **No `edit_product.php` / `order_details.php`** — both are referenced only in
  commented-out links in the admin pages; they were never part of the upload.
- Payment is a placeholder (checkout records the order only).
