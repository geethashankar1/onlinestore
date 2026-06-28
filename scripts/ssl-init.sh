#!/usr/bin/env bash
# scripts/ssl-init.sh
# Issue the first Let's Encrypt certificate and switch Nginx to HTTPS.
# Run ONCE on the server AFTER the stack is up and your DNS A record
# points to this server's IP.
#
# Usage:
#   bash /opt/eshop/scripts/ssl-init.sh yourdomain.com admin@yourdomain.com

set -euo pipefail

DOMAIN="${1:?Usage: ssl-init.sh <domain> <email>}"
EMAIL="${2:?Usage: ssl-init.sh <domain> <email>}"
APP_DIR="/opt/eshop"

echo "======================================================"
echo " SSL initialisation for: $DOMAIN"
echo " Contact email:          $EMAIL"
echo "======================================================"

# ── 1. Confirm Nginx is running ───────────────────────────────
echo ""
echo "▶ Checking Nginx is up…"
docker compose -f "$APP_DIR/docker-compose.prod.yml" up -d nginx web db
sleep 3
docker ps | grep eshop_nginx || { echo "ERROR: Nginx container not running."; exit 1; }

# ── 2. Substitute domain in the HTTP-only config ─────────────
echo ""
echo "▶ Patching nginx/default.conf with domain '$DOMAIN'…"
sed -i "s/YOUR_DOMAIN/$DOMAIN/g" "$APP_DIR/nginx/default.conf"
docker exec eshop_nginx nginx -s reload

# ── 3. Issue certificate ──────────────────────────────────────
echo ""
echo "▶ Requesting certificate from Let's Encrypt…"
docker compose -f "$APP_DIR/docker-compose.prod.yml" run --rm certbot certonly \
  --webroot \
  --webroot-path /var/www/certbot \
  --email "$EMAIL" \
  --agree-tos \
  --no-eff-email \
  -d "$DOMAIN" \
  -d "www.$DOMAIN"

echo "   Certificate issued successfully."

# ── 4. Switch to HTTPS config ─────────────────────────────────
echo ""
echo "▶ Activating HTTPS Nginx config…"
sed "s/YOUR_DOMAIN/$DOMAIN/g" "$APP_DIR/nginx/ssl.conf" > "$APP_DIR/nginx/default.conf"
docker exec eshop_nginx nginx -s reload
echo "   Nginx reloaded with HTTPS config."

# ── 5. Verify ─────────────────────────────────────────────────
echo ""
echo "▶ Verifying HTTPS…"
sleep 2
curl -sSo /dev/null -w "HTTP status: %{http_code}\n" "https://$DOMAIN" \
  || echo "   (curl check failed — double-check DNS propagation)"

echo ""
echo "======================================================"
echo " Done! Your site is live at https://$DOMAIN"
echo " Auto-renewal is handled by the certbot container."
echo "======================================================"
