#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# Install dependencies on first start or after a fresh clone.
	if [ -f composer.json ] && [ -z "$(ls -A vendor/ 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Development only: the JWT key pair is generated once and kept out of git. In production the
	# keys come from the environment (ADR 0008).
	if [ "$APP_ENV" = 'dev' ] && [ -f bin/console ] && [ ! -f config/jwt/private.pem ]; then
		php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction || true
	fi

	# Let both the web server and the host user write to var/.
	mkdir -p var/cache var/log
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var
fi

exec docker-php-entrypoint "$@"
