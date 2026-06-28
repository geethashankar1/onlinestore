#!/usr/bin/env bash
# scripts/setup-server.sh
# One-time setup for a fresh Ubuntu/Debian VPS.
# Run as root (or sudo) on the server ONCE after spinning up the droplet.
#
# Usage:
#   ssh root@YOUR_SERVER_IP
#   apt-get update && apt-get install -y curl
#   curl -sSL https://raw.githubusercontent.com/YOUR_USERNAME/YOUR_REPO/main/scripts/setup-server.sh | bash

set -euo pipefail
echo "======================================================"
echo " My E-Shop — Server Bootstrap"
echo "======================================================"

# ── 1. Install Docker ─────────────────────────────────────────
echo ""
echo "▶ Installing Docker…"
curl -fsSL https://get.docker.com | sh
systemctl enable --now docker

# Install Docker Compose v2 plugin if not already present
if ! docker compose version &>/dev/null; then
  apt-get install -y docker-compose-plugin
fi
echo "   Docker $(docker --version) installed."
echo "   Compose $(docker compose version) installed."

# ── 2. Create deploy user (optional but recommended) ─────────
echo ""
echo "▶ Creating deploy user 'eshop'…"
if ! id eshop &>/dev/null; then
  useradd -m -s /bin/bash eshop
  usermod -aG docker eshop
  mkdir -p /home/eshop/.ssh
  # Copy root's authorized_keys so the same SSH key works for the deploy user
  cp ~/.ssh/authorized_keys /home/eshop/.ssh/ 2>/dev/null || true
  chmod 700 /home/eshop/.ssh
  chmod 600 /home/eshop/.ssh/authorized_keys 2>/dev/null || true
  chown -R eshop:eshop /home/eshop/.ssh
  echo "   User 'eshop' created."
else
  echo "   User 'eshop' already exists — skipping."
fi

# ── 3. Create app directory ───────────────────────────────────
echo ""
echo "▶ Setting up /opt/eshop…"
mkdir -p /opt/eshop/nginx /opt/eshop/db /opt/eshop/scripts
chown -R eshop:eshop /opt/eshop
echo "   Directory ready."

# ── 4. Firewall ───────────────────────────────────────────────
echo ""
echo "▶ Configuring UFW firewall…"
apt-get install -y ufw
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
echo "   Firewall enabled (SSH + 80 + 443 open)."

# ── 5. Set GHCR credentials environment for deploy ───────────
echo ""
echo "▶ GHCR authentication setup…"
echo "   Add the following to /opt/eshop/.env (as eshop user):"
echo ""
echo "   GHCR_USER=your-github-username"
echo "   GHCR_PAT=ghp_your_personal_access_token   # read:packages scope"
echo ""

# ── 6. SSL cron job for auto-renewal ─────────────────────────
echo ""
echo "▶ Installing certbot renewal cron…"
# Reload nginx after renewal so the new cert is picked up
(crontab -l 2>/dev/null; echo "0 3 * * * docker exec eshop_nginx nginx -s reload") | crontab -
echo "   Cron job added (nginx reload nightly at 03:00)."

# ── Done ──────────────────────────────────────────────────────
echo ""
echo "======================================================"
echo " Bootstrap complete!"
echo ""
echo " Next steps:"
echo "  1. Switch to the eshop user:  su - eshop"
echo "  2. Clone your repo:           git clone https://github.com/YOUR_USERNAME/YOUR_REPO /opt/eshop"
echo "  3. Create the env file:       nano /opt/eshop/.env"
echo "     (see .env.example for required variables + add GHCR_USER / GHCR_PAT)"
echo "  4. Start the stack:           cd /opt/eshop && docker compose -f docker-compose.prod.yml up -d"
echo "  5. Get SSL:                   bash /opt/eshop/scripts/ssl-init.sh your-domain.com your@email.com"
echo "======================================================"
