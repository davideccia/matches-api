#!/usr/bin/env bash
set -e

cd /var/www

gosu www-data php artisan optimize

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    gosu www-data php artisan migrate --force
fi

gosu www-data php artisan storage:link --force 2>/dev/null || true

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
