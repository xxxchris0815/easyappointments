# Production image for this Easy!Appointments fork.
# App files are baked into /usr/src/easyappointments and synced into
# /var/www/html at container start so nginx can share the same volume.

FROM php:8.3-fpm-bookworm

WORKDIR /usr/src/easyappointments

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        gnupg \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libldap2-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        rsync \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure ldap --with-libdir=lib/$(uname -m)-linux-gnu/ \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        gettext \
        intl \
        ldap \
        mysqli \
        pdo_mysql \
        soap \
        sockets \
        zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY docker/production/php-ini-overrides.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/production/entrypoint.sh /usr/local/bin/ea-entrypoint
RUN chmod +x /usr/local/bin/ea-entrypoint

COPY . /usr/src/easyappointments

# Bake fork build identity (pass via compose build args from host git).
ARG EA_BUILD_ID=dev
ARG EA_GIT_COMMIT=local
ARG EA_GIT_BRANCH=local
ARG EA_BUILD_TIME=
RUN printf '%s\n' \
    '<?php defined('"'"'BASEPATH'"'"') or exit('"'"'No direct script access allowed'"'"');' \
    '' \
    "\$config['build_id'] = '${EA_BUILD_ID}';" \
    "\$config['build_commit'] = '${EA_GIT_COMMIT}';" \
    "\$config['build_branch'] = '${EA_GIT_BRANCH}';" \
    "\$config['build_time'] = '${EA_BUILD_TIME}';" \
    > /usr/src/easyappointments/application/config/build.php

RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
        --no-scripts \
    && npm ci \
    && npx gulp compile \
    && rm -rf node_modules \
    && mkdir -p \
        storage/backups \
        storage/cache \
        storage/logs \
        storage/sessions \
        storage/uploads \
    && chown -R www-data:www-data /usr/src/easyappointments/storage

WORKDIR /var/www/html

ENTRYPOINT ["ea-entrypoint"]
CMD ["php-fpm"]
