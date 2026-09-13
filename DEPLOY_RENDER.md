# Deploying to Render (free tier)

Migration target after DigitalOcean. Unlike the droplet, there is no server to
manage — Render runs the container, terminates TLS, and handles the domain. The
database and image storage move to separate free services because Render's free
tier provides neither.

```
GitHub push ─► GitHub Actions ─► GHCR image ─► Render Web Service ─┬─► TiDB Serverless
                 (build+lint)                    (PHP 8.3/Apache)  └─► Cloudinary
```

## What changed from the droplet setup

| Piece | DigitalOcean | Render |
|---|---|---|
| Web server | nginx container + certbot | Render's edge — **nginx/certbot no longer used** |
| Database | `mysql:8.0` container | TiDB Cloud Serverless (external, TLS) |
| Uploaded images | `uploads_data` Docker volume | Cloudinary (free disk is ephemeral) |
| CI/CD | Jenkins on the same box | GitHub Actions |
| TLS | Let's Encrypt via certbot | Render managed certificate |

`docker-compose.prod.yml`, `docker-compose.staging.yml`, `nginx/` and the
`Jenkinsfile` are **not used by Render**. They stay in the repo for local
development (`docker compose up`) and as a record of the Jenkins pipeline.

## Known limits of the free tier

- **Services sleep after 15 minutes idle**, and the next request takes ~50s to
  wake. Unavoidable on the free plan — the paid Starter tier removes it.
- **No persistent disk.** This is why images go to Cloudinary. Anything else
  written to the container filesystem is lost on restart.
- TiDB Serverless is free with a monthly Request Unit quota. It does **not**
  power off when idle — which is why we left Aiven, whose free plan does.

---

## Step 1 — Database (TiDB Cloud Serverless)

1. Sign up at https://tidbcloud.com and create a **Serverless** cluster
   (region `ap-southeast-1` for India).
2. Click **Connect**, generate a password, and note **Host**, **Port** (4000),
   **User** (`<prefix>.root`) and **Password**.
3. Create the database and load the schema:

```bash
docker run --rm -i mysql:8.0 mysql   --host=<HOST> --port=4000 --user=<PREFIX>.root --password=<PASS>   --ssl-mode=VERIFY_IDENTITY --ssl-ca=/etc/pki/tls/certs/ca-bundle.crt   -e "CREATE DATABASE IF NOT EXISTS eshop"

docker run --rm -i -v "$PWD:/w" -w /w mysql:8.0 sh -c   "mysql --host=<HOST> --port=4000 --user=<PREFIX>.root --password=<PASS>    --ssl-mode=VERIFY_IDENTITY --ssl-ca=/etc/pki/tls/certs/ca-bundle.crt eshop" < db/schema.sql
```

> Use `db/schema.sql`, not `init.sql` + migrations: migration 002 uses stored
> procedures, which TiDB does not support. `schema.sql` declares the final shape
> directly and runs on both MySQL and TiDB.

## Step 2 — Image storage (Cloudinary)

1. Sign up at https://cloudinary.com (free tier).
2. Dashboard → **Programmable Media → API Keys**.
3. Note **Cloud name**, **API Key**, **API Secret**.

Nothing to configure beyond that — `my_eshop/config/media.php` uploads via the
REST API and stores the returned URL in the `image` column. Without these vars
set, it silently falls back to writing `uploads/` as before, which is what keeps
local `docker compose up` working unchanged.

## Step 3 — GHCR image

Render pulls a pre-built image, so GHCR must be readable:

- Push once to `main` so GitHub Actions builds and pushes `:latest`, **or**
- Make the package public: GitHub → your profile → Packages → `onlinestore` →
  Package settings → Change visibility → Public.

If you keep it private, add a Render registry credential (Render dashboard →
Settings → Registry Credentials) with a GitHub PAT that has `read:packages`.

## Step 4 — Render web service

1. https://dashboard.render.com → **New → Web Service**.
2. Choose **Deploy an existing image from a registry**.
3. Image URL: `ghcr.io/geethashankar1/onlinestore:latest`
4. Name: `myeshop`, Region: closest to you, Instance type: **Free**.
5. Add **Environment Variables**:

| Key | Value |
|---|---|
| `DB_HOST` | TiDB gateway host |
| `DB_PORT` | `4000` |
| `DB_USER` | `<prefix>.root` |
| `DB_PASS` | TiDB password |
| `DB_NAME` | `eshop` |
| `DB_SSL` | `true` |
| `DB_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt` |
| `CLOUDINARY_CLOUD_NAME` | from Cloudinary |
| `CLOUDINARY_API_KEY` | from Cloudinary |
| `CLOUDINARY_API_SECRET` | from Cloudinary |
| `JWT_SECRET` | a long random string (used by the mobile API) |
| `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` | optional |
| `AUTHNET_LOGIN_ID` / `AUTHNET_TRANS_KEY` / `AUTHNET_CLIENT_KEY` | optional |

6. **Create Web Service.** First deploy takes a few minutes.

> TiDB serves a publicly trusted certificate, so `DB_SSL_CA` points at the
> container's system CA bundle and you get full verification with no
> provider-specific CA file to maintain.

## Step 5 — Deploy hook + GitHub Actions

1. Render service → **Settings → Deploy Hook** → copy the URL.
2. GitHub repo → Settings → Secrets and variables → Actions → **New secret**:
   - `RENDER_DEPLOY_HOOK_PROD` = that URL
   - `RENDER_DEPLOY_HOOK_STAGING` = the staging service's hook (if you create one)

From then on, a push to `main` lints, builds, pushes to GHCR, and tells Render to
pull. A push to `develop` does the same against the staging service.

## Step 6 — Custom domain

Render supports custom domains with free managed TLS, even on free instances.

1. Render service → **Settings → Custom Domains → Add** `myeshopstore.online`
   and `www.myeshopstore.online`.
2. Render shows the DNS records to create. At your registrar, **replace** the old
   droplet A record (`64.227.187.60`):

| Type | Host | Value |
|---|---|---|
| A | `@` | (IP Render gives you) |
| CNAME | `www` | `<your-service>.onrender.com` |

3. Wait for Render to show "Certificate issued" — usually minutes.

## Step 7 — Verify

```bash
curl -I https://myeshopstore.online          # expect 200 (first hit may take ~50s)
curl -s https://myeshopstore.online/api/products | head
```

Then in the browser: log in as `admin` (**change the seed password immediately**),
add a product with an image, and confirm the image URL points at
`res.cloudinary.com`. That last check is the one that proves the ephemeral-disk
problem is actually solved — if the URL is `/uploads/...`, the Cloudinary env
vars aren't reaching the container.

---

## Troubleshooting

**502 / "Application failed to respond"** — Apache isn't on Render's port. The
Dockerfile sets `Listen ${PORT}`; confirm `PORT` isn't overridden to something
Apache isn't bound to, and check the Render logs.

**"Database connection failed"** — set `APP_DEBUG=true` temporarily to see the
real error in the browser, or read the Render logs (the detail is always logged).
Usual causes: wrong port, a mistyped password (watch for `I`/`l`/`1` confusion),
or `DB_SSL` unset — TiDB refuses plaintext connections.

**Images upload but vanish** — the Cloudinary vars aren't set, so it fell back to
the ephemeral local disk. Check the stored value in `products.image`: a Cloudinary
row starts with `https://res.cloudinary.com/`.

**First request is very slow** — the 15-minute spin-down. A free uptime pinger
(cron-job.org) hitting the site every 10 minutes keeps it warm, though that is
working against the free tier's intent; the honest fix is the paid tier.

**Old images 404 after migration** — rows created on the droplet reference local
filenames whose files died with the droplet. `media_url()` falls back to the
placeholder for those. Re-upload the images, or clear the `image` column.
