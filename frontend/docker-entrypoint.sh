#!/bin/sh
# Keeps node_modules, which lives in a Docker volume for speed, in step with package-lock.json.
# `npm ci` wipes and reinstalls everything, so it only runs when the lock file has changed.
set -e

stamp=node_modules/.package-lock.sha256
current=$(sha256sum package-lock.json | cut -d ' ' -f 1)

if [ ! -f "$stamp" ] || [ "$(cat "$stamp")" != "$current" ]; then
    echo "Installing the dependencies of package-lock.json..."
    npm ci --no-audit --no-fund
    echo "$current" > "$stamp"
fi

exec "$@"
