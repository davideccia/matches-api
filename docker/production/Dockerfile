# ─── Stage 1: Builder ─────────────────────────────────────────────────────────
FROM php:8.5-fpm-bookworm AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        unzip \
        libpq-dev \
        libonig-dev \
        libssl-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libicu-dev \
        libzip-dev \
        libmagickwand-dev \
        libgd-dev \
        libexif-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
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
    && cd $(mktemp -d) \
        && pecl download redis \
        && tar xzf redis-*.tgz \
        && cd redis-* \
        && phpize \
        && ./configure --enable-redis-igbinary \
        && make -j$(nproc) \
        && make install \
    && pecl install imagick \
    && docker-php-ext-enable igbinary redis imagick \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /var/www

COPY . /var/www
COPY .env.example /var/www/.env

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

# ─── Stage 2: Production ──────────────────────────────────────────────────────
FROM php:8.5-fpm-bookworm AS production

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq5 \
        libicu72 \
        libzip4 \
        libmagickwand-6.q16-6 \
        libgd3 \
        libexif12 \
        supervisor \
        gosu \
        curl \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/bin/docker-php-ext-* /usr/local/bin/

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=caddy:2-alpine /usr/bin/caddy /usr/bin/caddy

# Caddy stores ACME certificates and keys under $XDG_DATA_HOME — mount /data as a
# volume, otherwise every container restart requests a fresh certificate.
ENV XDG_DATA_HOME=/data \
    XDG_CONFIG_HOME=/config

COPY docker/production/Caddyfile /etc/caddy/Caddyfile
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

COPY --from=builder /var/www /var/www

WORKDIR /var/www

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

VOLUME ["/data"]

EXPOSE 80 443 443/udp

# Runtime secrets and config must be injected via environment variables at deploy time,
# because .env.example is baked into the image as a default .env during the build stage.
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
  CMD curl -f http://localhost/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
