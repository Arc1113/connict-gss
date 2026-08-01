FROM composer:2 AS composer
FROM postgres:16-bookworm AS postgres-client

FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        git \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        pcntl \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=postgres-client /usr/lib/postgresql/16/bin/pg_dump /usr/local/bin/pg_dump
COPY --from=postgres-client /usr/lib/postgresql/16/bin/psql /usr/local/bin/psql
COPY --from=postgres-client /usr/lib/postgresql/16/bin/createdb /usr/local/bin/createdb
COPY --from=postgres-client /usr/lib/postgresql/16/bin/dropdb /usr/local/bin/dropdb
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/gss-uploads.ini

WORKDIR /app

EXPOSE 8000 8080
