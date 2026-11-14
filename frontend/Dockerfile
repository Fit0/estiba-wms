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

COPY --link --from=frontend_build /app/dist /srv
COPY --link Caddyfile /etc/caddy/Caddyfile

EXPOSE 80
