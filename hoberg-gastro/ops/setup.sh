#!/usr/bin/env bash
# Hoberg Gastro - Phase 0 Server-Grundhaertung + Docker + Coolify
# Voraussetzung: SSH-Key-Login funktioniert bereits (siehe phase0-server-setup.md Schritt 2)
# Ausfuehren als root auf einem frischen Ubuntu 24.04 LTS minimal.
set -euo pipefail

echo "== System aktualisieren =="
apt-get update
apt-get upgrade -y

echo "== Automatische Sicherheitsupdates =="
apt-get install -y unattended-upgrades ufw fail2ban
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "== Firewall: nur SSH/HTTP/HTTPS =="
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "== Fail2ban gegen SSH-Bruteforce =="
systemctl enable --now fail2ban

echo "== Docker installieren =="
apt-get install -y ca-certificates curl gnupg
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  > /etc/apt/sources.list.d/docker.list
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

echo "== Coolify installieren =="
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash

echo "== Fertig. Coolify-Dashboard-URL siehe Ausgabe oben. =="
