# Pinned to an exact patch release so builds are reproducible
FROM php:8.5.13-apache

# Composer uses unzip and git to download packages
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*

# PDO driver for MariaDB
RUN docker-php-ext-install pdo_mysql

# Composer binary from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache modules and hardening, plus the production php.ini as the base
RUN a2enmod rewrite \
    && sed -ri \
        -e 's/^ServerTokens .*/ServerTokens Prod/' \
        -e 's/^ServerSignature .*/ServerSignature Off/' \
        /etc/apache2/conf-available/security.conf \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Our config files last: they change most often, so edits rebuild only these layers
COPY docker/apache/site.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

WORKDIR /var/www/html

