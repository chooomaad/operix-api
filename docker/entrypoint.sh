#!/usr/bin/env bash
set -e

# Le port fourni par Render (défaut 80 en local) : appliqué à Apache au démarrage.
export PORT="${PORT:-80}"
sed -ri "s!Listen [0-9]+!Listen ${PORT}!g" /etc/apache2/ports.conf
sed -ri "s!:80>!:${PORT}>!g" /etc/apache2/sites-available/000-default.conf

# Caches Laravel (config/routes/views) pour la performance
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Migrations (idempotent) — crée aussi rôles/permissions (semés par migration)
php artisan migrate --force

# Seed idempotent (firstOrCreate) : tenant TCN + admin, seulement si demandé
if [ "${RUN_SEED:-false}" = "true" ]; then
  php artisan db:seed --force || true
fi

# Lien de stockage public (photos/justificatifs servis via /storage)
php artisan storage:link || true

exec apache2-foreground
