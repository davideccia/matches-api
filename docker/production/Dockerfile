# syntax=docker/dockerfile:1
# Production image: Laravel API + Reverb + Horizon + Log Viewer.
#
# Build from the REPOSITORY ROOT (the context must include composer.json):
#   DOCKER_BUILDKIT=1 docker build -f docker/production/Dockerfile -t matches-api .
#
# The build context is filtered by docker/production/Dockerfile.dockerignore.
#
# TLS is terminated by a reverse proxy in FRONT of this container, which maps
# hostnames onto the two PUBLIC ports:
#   api.<domain>     -> :80    nginx -> php-fpm  (API; /horizon e /log-viewer
#                              qui rispondono 404)
#   reverb.<domain>  -> :8080  Reverb websockets
#
# La terza porta NON e' pubblica:
#   -                -> :8081  ADMIN. Stessa app senza i 404, cioe' Horizon e
#                              log-viewer. Va pubblicata solo su loopback e
#                              raggiunta con un LocalForward SSH: vedi
#                              docker/production/README.md §9.

# ─── Stage 1: Builder ─────────────────────────────────────────────────────────
FROM php:8.5-fpm-bookworm AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        libpq-dev \
        libonig-dev \
        libssl-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libicu-dev \
        libzip-dev \
        libgd-dev \
        libexif-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    # NOTE: opcache is NOT listed here. In php:8.5 it is statically compiled into
    # the binary and enabled by default, so docker-php-ext-install opcache fails.
    # Its tuning (JIT, validate_timestamps) lives in php.ini instead.
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        bcmath \
        gd \
        exif \
        pcntl \
        posix \
    && pecl install igbinary \
    # --configureoptions links the igbinary serializer into the redis extension.
    # Horizon/cache payloads are serialized with igbinary, so this is required;
    # a plain `pecl install redis` would build without it.
    # Built serially on purpose: a parallel MAKEFLAGS races the extension's
    # install-modules target and fails with "cp: cannot stat 'modules/*'".
    && pecl install --configureoptions 'enable-redis-igbinary="yes"' redis \
    && docker-php-ext-enable igbinary redis \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Pinned and checksum-verified by the upstream image, unlike `curl | php`.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Dependencies first: this layer is cached until composer.json/lock change, so
# editing application code no longer reinstalls the whole vendor tree.
COPY composer.json composer.lock ./
# The cache mount lets a retried build reuse already-downloaded archives instead
# of re-hitting api.github.com, which returns 504 under unauthenticated load.
# COMPOSER_MAX_PARALLEL_HTTP is lowered for the same reason.
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    COMPOSER_MAX_PARALLEL_HTTP=6 \
    composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY . .

# Deferred to here because the post-autoload-dump hook runs
# `artisan package:discover`, which needs the application source present.
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer dump-autoload --optimize --no-dev --classmap-authoritative

# log-viewer serve i suoi asset come file statici da public/vendor/log-viewer/
# (`assets_path` in config/log-viewer.php), ma quella cartella NON e' nel repo e
# non la crea nessuno: il tag `log-viewer-assets` e' l'unico modo di popolarla, e
# composer.json non lo invoca (qui sopra girano `composer install --no-scripts` e
# `dump-autoload`). Senza questa riga /log-viewer si apre senza CSS ne' JS.
#
# Horizon NON serve: dalla 5.x inlinea CSS e JS nel layout leggendoli da
# vendor/laravel/horizon/dist/, che composer install ha gia' messo nell'immagine.
RUN php artisan vendor:publish --tag=log-viewer-assets --no-interaction --force

# ─── Stage 2: Production ──────────────────────────────────────────────────────
FROM php:8.5-fpm-bookworm AS production

LABEL org.opencontainers.image.title="matches-api" \
    org.opencontainers.image.description="Matches - Laravel REST API server"

# Runtime counterparts of the builder's -dev packages, plus the process
# supervisor, web server and the privilege-dropping helper.
#
# postgresql-client comes from PGDG rather than Debian: bookworm ships client 15,
# and pg_dump refuses to dump a newer server. pg_dump 18 handles 18 and older,
# so it is the safe choice. spatie/laravel-backup shells out to it for the
# nightly `backup:run` scheduled in routes/console.php (gzip is already in the
# base image, and the zip extension is built in the builder stage).
#
# apt reads ASCII-armored keys directly via signed-by, so gnupg is not needed.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl; \
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
        -o /usr/share/keyrings/pgdg.asc; \
    echo 'deb [signed-by=/usr/share/keyrings/pgdg.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main' \
        > /etc/apt/sources.list.d/pgdg.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpq5 \
        libicu72 \
        libzip4 \
        libgd3 \
        libexif12 \
        nginx \
        supervisor \
        gosu \
        postgresql-client-18; \
    apt-get autoremove -y; apt-get clean; \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Order matters: the baseline php.ini lands first, then zz-app.ini overrides it.
# The wholesale conf.d copy above would otherwise clobber our tuning.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/production/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/production/nginx-app.conf /etc/nginx/app.conf
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY --from=builder /var/www /var/www

WORKDIR /var/www

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    # public/ stays root-owned, so www-data cannot create this at runtime.
    # Excluded from the build context because the committed symlink targets the
    # Sail path (/var/www/html/...); recreate it here for /storage/* URLs.
    && ln -sfn /var/www/storage/app/public /var/www/public/storage

# Reverb's LISTEN port. Defaulted here so supervisord can always expand
# %(ENV_REVERB_SERVER_PORT)s even when the deploy does not inject it.
ENV REVERB_SERVER_PORT=8080

# Documentazione, non un vincolo: EXPOSE non pubblica nulla. :8081 va
# pubblicata SOLO su loopback (`127.0.0.1:8081:8081`), altrimenti Docker
# scrive in DOCKER-USER/nat e la porta e' raggiungibile da internet
# scavalcando le regole INPUT di ufw.
EXPOSE 80 8080 8081

# Deliberately goes through nginx AND php-fpm, so a dead PHP pool fails the
# check instead of being masked by a static response.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD curl -fsS http://127.0.0.1/up || exit 1

# No .env exists in this image: every setting (APP_KEY, DB_*, REDIS_*,
# LOG_CHANNEL=daily, LARAVEL_PDF_DRIVER=dompdf, HORIZON_*/LOG_VIEWER_*
# credentials) must be injected as environment variables at deploy time.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
