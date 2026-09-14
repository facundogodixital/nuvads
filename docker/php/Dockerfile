FROM composer:2 AS composer

FROM php:8.4-fpm-bookworm

ARG LOCAL_UID
ARG LOCAL_GID

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libssl-dev \
    && docker-php-ext-install -j2 pdo_mysql intl zip pcntl bcmath opcache \
    && pecl install redis-6.3.0 \
    && pecl install mongodb-2.5.2 \
    && docker-php-ext-enable redis mongodb \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# PHP y los comandos de Composer crean archivos con el usuario del host.
RUN groupmod --gid "${LOCAL_GID}" www-data \
    && usermod --uid "${LOCAL_UID}" --gid "${LOCAL_GID}" www-data \
    && mkdir -p /tmp/composer \
    && chown www-data:www-data /tmp/composer \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && sed -i '/^user = /d; /^group = /d' /usr/local/etc/php-fpm.d/www.conf

COPY docker/php/development.ini /usr/local/etc/php/conf.d/99-development.ini

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html
USER www-data
