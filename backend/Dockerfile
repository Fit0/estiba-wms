#syntax=docker/dockerfile:1

# The user the production image runs as (ADR 0053). Not root, and with a fixed number so a platform can
# say so in its own settings.
ARG APP_UID=10001

# FrankenPHP: PHP 8.4 embedded in the Caddy web server.
FROM dunglas/frankenphp:1-php8.4 AS frankenphp_upstream

# What every image needs to run the application: the PHP extensions, the settings and the entrypoint.
# The production image is made from this stage alone, so it carries no Composer, no Git and no unzip.
FROM frankenphp_upstream AS frankenphp_runtime

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends acl file \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    install-php-extensions \
        apcu \
        intl \
        opcache \
        pdo_pgsql \
        redis \
        soap \
        zip

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/caddy/Caddyfile

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# Base image of the ones that build or develop: the runtime plus Composer, Git and unzip.
FROM frankenphp_runtime AS frankenphp_base

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; install-php-extensions @composer

ENV COMPOSER_ALLOW_SUPERUSER=1

# Development image: Xdebug available, enabled on demand with XDEBUG_MODE.
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
RUN install-php-extensions xdebug

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

# Build stage of the production image (ADR 0052): installs the dependencies without the development
# ones, makes the autoloader a class map and compiles the Symfony container. Nothing of it but /app
# reaches the final image.
FROM frankenphp_base AS frankenphp_build

ARG APP_UID

ENV APP_ENV=prod
ENV APP_DEBUG=0

# The dependencies come before the code, so a change in src/ does not install them again.
COPY --link composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-autoloader --no-scripts --no-progress --no-interaction

# The code is taken one directory at a time: tests, tools and local files stay out.
COPY --link .env.example ./
COPY --link bin bin/
COPY --link config config/
COPY --link migrations migrations/
COPY --link public public/
COPY --link src src/
COPY --link translations translations/

# The container is compiled here so the first request does not pay for it. The compile reads the
# defaults of .env.example, which hold no secret, and the file is emptied afterwards: the real
# values come from the environment when the container starts.
RUN set -eux; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    cp .env.example .env; \
    APP_SECRET=build-only php bin/console cache:warmup --env=prod --no-debug; \
    : > .env; \
    mkdir -p var/log; \
    chown -R "$APP_UID:$APP_UID" var

# Production image: the runtime and the application, ready to run. Configuration and secrets come from
# the environment. The migrations are in the image and are run by the deployment, not by the start.
FROM frankenphp_runtime AS frankenphp_prod

ARG APP_UID

ENV APP_ENV=prod
ENV APP_DEBUG=0
# TLS is ended in front of the container, by the platform or a proxy. Caddy listens on plain HTTP and
# does not try to get a certificate for "localhost". The port is above 1024, so the process needs no
# privilege to take it (ADR 0053).
ENV SERVER_NAME=:8080
# Caddy keeps its state in a directory of its own, which the user of the image owns. The ones the base
# image uses are volumes, and what is changed in a volume at build time is lost.
ENV XDG_CONFIG_HOME=/var/caddy/config
ENV XDG_DATA_HOME=/var/caddy/data

RUN set -eux; \
    useradd --uid "$APP_UID" --user-group --no-create-home --shell /usr/sbin/nologin app; \
    mkdir -p "$XDG_CONFIG_HOME" "$XDG_DATA_HOME"; \
    chown -R app:app /var/caddy

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/
# The code stays owned by root, so the process cannot change it: it owns var/ only, which the build
# stage prepared.
COPY --link --from=frankenphp_build /app /app

USER app

EXPOSE 8080
