#syntax=docker/dockerfile:1.4

FROM php:8.2-alpine

WORKDIR /app

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN set -eux; \
    chmod +x /usr/local/bin/install-php-extensions; \
    install-php-extensions \
        pdo_pgsql \
    ;

COPY --link . ./
