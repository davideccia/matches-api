#!/usr/bin/env bash
set -e

cd /var/www

# A bind-mounted storage/ starts empty and root-owned, unlike a named volume
# which inherits the image content. Recreate the tree and fix ownership on every
# boot. storage/logs matters especially: with LOG_CHANNEL=daily it is where the
# /log-viewer dashboard reads from, so it should be a persistent volume.
mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/app/backup-temp \
    storage/framework/{cache/data,sessions,views} \
    storage/logs
chown -R www-data:www-data storage bootstrap/cache

# Caches config, routes, events and views. Runs at boot, not at build time,
# because the configuration comes entirely from runtime environment variables —
# no .env is baked into the image.
gosu www-data php artisan optimize

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    gosu www-data php artisan migrate --force
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
