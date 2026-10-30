FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        postgresql-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        zip \
        unzip \
        git \
        bash \
        netcat-openbsd \
    && docker-php-ext-install pdo pdo_pgsql bcmath intl pcntl opcache \
    && docker-php-ext-enable opcache \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# ---------------------------------------------------------------------------
FROM base AS dev

ENV APP_ENV=local

# The project directory is bind-mounted from the host in development, owned
# by the host user. Re-point www-data at that same uid/gid so the php-fpm
# worker can write to storage/bootstrap/cache without touching host perms.
RUN deluser www-data 2>/dev/null; \
    delgroup www-data 2>/dev/null; \
    addgroup -g 1000 www-data && \
    adduser -D -H -u 1000 -G www-data -s /sbin/nologin www-data

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=production

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-dev --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
