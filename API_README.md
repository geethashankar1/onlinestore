# My E-Shop — JSON API (Phase 1)

A thin REST/JSON layer over your existing PHP + MySQL backend, for the mobile app to consume. Your website is untouched and keeps working as-is.

- **Base URL (dev):** `http://localhost:8080/api`
- **Auth model:** stateless **JWT**. Login/register return a `token`; send it as `Authorization: Bearer <token>` on protected routes. Token lifetime: 7 days. (No PHP sessions — the API is stateless.)
- **All responses are JSON.** Errors look like `{"error":"..."}` with an appropriate HTTP status.

## Endpoints

| Method | Path | Auth | Body | Returns |
|---|---|---|---|---|
| GET | `/api/products` | — | — (`?search=` optional) | `{ products:[ {id,name,description,price,image,image_url,created_at} ] }` |
| GET | `/api/products/{id}` | — | — | `{ product:{…} }` or 404 |
| POST | `/api/auth/register` | — | `{username,email,password}` | 201 `{ token, user:{id,username,email,is_admin} }` |
| POST | `/api/auth/login` | — | `{email_or_username,password}` | `{ token, user:{…} }` or 401 |
| GET | `/api/me` | ✅ | — | `{ user:{…} }` |
| POST | `/api/orders` | ✅ | `{shipping_address, items:[{product_id,quantity}]}` | 201 `{ order_id, total_amount, status }` |
| GET | `/api/orders` | ✅ | — | `{ orders:[ {id,total_amount,shipping_address,status,created_at} ] }` |

## Examples (curl)

```bash
# list products
curl http://localhost:8080/api/products

# login with the seeded admin -> copy the token from the response
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email_or_username":"admin@example.com","password":"admin123"}'

# authenticated call
curl http://localhost:8080/api/me -H "Authorization: Bearer PASTE_TOKEN_HERE"

# place an order (prices are recalculated server-side from the DB)
curl -X POST http://localhost:8080/api/orders \
  -H "Content-Type: application/json" -H "Authorization: Bearer PASTE_TOKEN_HERE" \
  -d '{"shipping_address":"12 Test St, Hyderabad","items":[{"product_id":1,"quantity":2}]}'
```

## How to test fast
- **Browser:** open `http://localhost:8080/api/products` — you should see JSON.
- **Script:** run `bash test_api.sh` (uses the seeded admin; no jq required).
- **Postman:** import `EShop_API.postman_collection.json`. Run **Login** first — it auto-saves the token into the `{{token}}` collection variable, so the protected requests just work.

## Security notes (read before deploying)
- **Order totals are computed server-side.** The client sends only `product_id` + `quantity`; the API looks up the current price in the DB. A client cannot fake prices.
- **Passwords** use `password_hash()` (bcrypt) — same as your web app. Never returned by the API.
- **Set a real JWT secret** before any public deployment. Add to the `web` service in `docker-compose.yml`:
  ```yaml
      environment:
        JWT_SECRET: ${JWT_SECRET:-please-change}
  ```
  and put a strong value in `.env` (`JWT_SECRET=...`). A dev fallback is baked in so it runs locally with zero config.
- **CORS is open (`*`) for development.** Restrict `Access-Control-Allow-Origin` to your real app/origin before launch. (Native iOS/Android don't enforce CORS, but your dev browser and any web build will.)
- **Optional debug:** set `API_DEBUG=1` in the `web` env to include exception detail in 500 responses while developing.

## Files (drop-in)
```
my_eshop/api/
├── .htaccess            # routes /api/* to index.php; passes Authorization header
├── index.php            # all routes
└── lib/
    ├── bootstrap.php     # JSON headers, CORS, DB (no session), helpers
    └── jwt.php           # HS256 JWT (no Composer dependency)
```
No Dockerfile/compose changes required: `mod_rewrite` + `AllowOverride All` are already in your image, and `my_eshop/` is bind-mounted, so the API is live as soon as the files are in place. If the container isn't running: `docker compose up -d`.
