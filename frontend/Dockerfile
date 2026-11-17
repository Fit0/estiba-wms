# The images of the frontend. `frontend_dev` is the development server that Compose runs. `frontend_prod`
# is the production image (ADR 0052): the app built once and served as static files by Caddy.

# The development image.
FROM node:24-alpine AS frontend_dev

WORKDIR /app

# Installs the dependencies when package-lock.json changes, and starts what it is told to start.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["sh", "/usr/local/bin/docker-entrypoint.sh"]
CMD ["npm", "run", "dev"]

EXPOSE 5173

# Build stage of the production image: installs exactly what package-lock.json says and builds the app.
# The app calls the API through relative paths, so nothing about the environment is baked in.
FROM node:24-alpine AS frontend_build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN npm run build

# The production image: the built files and a web server, and nothing of Node.
FROM caddy:2-alpine AS frontend_prod

# Not root (ADR 0053). Caddy listens on 8080, a port that needs no privilege, and keeps its state in a
# directory the user owns. The ones the base image uses are volumes, and what is changed in a volume at
# build time is lost.
ENV XDG_CONFIG_HOME=/var/caddy/config
ENV XDG_DATA_HOME=/var/caddy/data

RUN set -eux; \
    adduser -D -H -u 10001 app; \
    mkdir -p "$XDG_CONFIG_HOME" "$XDG_DATA_HOME"; \
    chown -R app:app /var/caddy

COPY --link --from=frontend_build /app/dist /srv
COPY --link Caddyfile /etc/caddy/Caddyfile

USER app

# The server is the process that has to answer. The port is the one Caddy listens on (PORT, 8080 by default).
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q -O /dev/null "http://127.0.0.1:${PORT:-8080}/" || exit 1

EXPOSE 8080
