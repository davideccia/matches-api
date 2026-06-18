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
    && pecl install --configureopts 'enable-redis-igbinary="yes"' redis \
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
        nginx \
        supervisor \
        gosu \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/bin/docker-php-ext-* /usr/local/bin/

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/production/nginx.conf /etc/nginx/sites-available/default
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

COPY --from=builder /var/www /var/www

WORKDIR /var/www

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 80 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
