#!/usr/bin/env bash
# Emite certificado Let's Encrypt e finaliza config HTTPS de um domínio de marketplace.
# Uso (no host, na raiz do projeto):
#   bash scripts/provision-tenant-ssl.sh julio.com.br

set -euo pipefail

DOMAIN="${1:?Informe o domínio, ex: julio.com.br}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

COMPOSE="docker compose"
APP_CONTAINER="express-app"
EMAIL="${TENANT_CERTBOT_EMAIL:-${CERTBOT_EMAIL:-admin@express.app.br}}"

echo "[ssl] Recarregando nginx (config HTTP)..."
$COMPOSE exec -T nginx nginx -s reload

echo "[ssl] Emitindo certificado Let's Encrypt para ${DOMAIN}..."
$COMPOSE run --rm certbot certonly \
  --webroot -w /var/www/certbot \
  -d "$DOMAIN" \
  --email "$EMAIL" \
  --agree-tos \
  --non-interactive \
  --keep-until-expiring

echo "[ssl] Gerando configuração HTTPS..."
$COMPOSE exec -T "$APP_CONTAINER" php artisan tenant:ssl-finalize "$DOMAIN"

echo "[ssl] Recarregando nginx (HTTPS)..."
$COMPOSE exec -T nginx nginx -s reload

echo "[ssl] Concluído: https://${DOMAIN}"
