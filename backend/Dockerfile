#syntax=docker/dockerfile:1

# FrankenPHP: PHP 8.4 embedded in the Caddy web server.
FROM dunglas/frankenphp:1-php8.4 AS frankenphp_upstream

# Base image shared by every environment.
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends acl file git unzip \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    install-php-extensions \
        @composer \
        apcu \
        intl \
        opcache \
        pdo_pgsql \
        redis \
        zip

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/caddy/Caddyfile

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# Development image: Xdebug available, enabled on demand with XDEBUG_MODE.
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
RUN install-php-extensions xdebug

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/
