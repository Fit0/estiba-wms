#!/bin/sh
# Creates backend/.env from .env.example with new random secrets. It never overwrites an existing
# .env, so running it twice is safe. Used by `make up` and by the CI jobs.
#
# Call it through the interpreter (`sh scripts/create-env.sh`): the executable bit is not kept on
# every platform.
set -eu

cd "$(dirname "$0")/.."

if [ -f .env ]; then
    echo "backend/.env already exists. Left as it is."
    exit 0
fi

random_secret() {
    od -An -N32 -tx1 /dev/urandom | tr -d ' \n'
}

sed \
    -e "s/^APP_SECRET=.*/APP_SECRET=$(random_secret)/" \
    -e "s/^JWT_PASSPHRASE=.*/JWT_PASSPHRASE=$(random_secret)/" \
    .env.example > .env

echo "Created backend/.env with new secrets."
