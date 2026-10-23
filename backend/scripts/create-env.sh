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

# A JWT key pair is encrypted with the passphrase it was made with. The new passphrase cannot open
# it, so tokens could not be signed. Dropping the old keys makes the stack generate new ones, which
# is safe because they only exist in development.
if [ -f config/jwt/private.pem ] || [ -f config/jwt/public.pem ]; then
    rm -f config/jwt/private.pem config/jwt/public.pem
    echo "Removed the JWT keys made with the old passphrase. Run 'make jwt-keys' or restart the stack to create new ones."
fi
