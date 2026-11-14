# 0052. Production images for the backend and the frontend

- Status: Accepted
- Date: 2026-11-14

## Context

Until now the project had one image for each service, and both were for development: the backend with Xdebug and Composer and with the code and `vendor/` mounted from the host, the frontend with Node running the Vite server. A deployment (E10-02) needs images that hold everything they run, are not changed after they are built, and carry nothing of development: no Composer, no Git, no test tools, no local secrets.

Three questions come with it. What goes in the backend image and what is left out. How the built frontend is served. And how the two are told where they are and what their secrets are, since an image cannot hold them.

## Options considered

For the backend: **one image for every use**, with a switch for development, or **separate targets of one Dockerfile** that share a base. The first is a smaller file and a bigger image, with Xdebug and Composer in production. For the PHP image: `dunglas/frankenphp` (Debian), which is what development uses, or its Alpine variant, which is smaller and not the same system as the one tested every day.

For the frontend: **static files on a CDN or a static host**, **nginx**, or **Caddy** in a small container. A static host is the simplest and puts the app on another origin than the API. The refresh token is a `SameSite=Strict` cookie that the API sets (ADR 0022), so the browser would have to send it across sites, and the API would need CORS. A container that serves the files and sends `/api` to the API keeps one origin, as the development server already does (ADR 0021). nginx or Caddy for that: Caddy is already what runs the API (FrankenPHP), its configuration is shorter, and it streams the Mercure connection without extra settings.

## Decision

**The backend `Dockerfile` has more stages, and development does not change.**

- `frankenphp_runtime` has what every image needs to run the application: the PHP extensions, the settings, the Caddy configuration and the entrypoint.
- `frankenphp_base` adds Composer, Git and unzip. `frankenphp_dev` is built on it as before.
- `frankenphp_build` installs the dependencies without the development ones, makes the autoloader a class map and compiles the Symfony container.
- `frankenphp_prod` is the runtime plus `/app` copied from the build stage. It has no Composer, no Git and no development packages.

**The code goes in by directory.** The build stage copies `bin`, `config`, `migrations`, `public`, `src` and `translations`. Tests, tool configuration and local files stay out. `backend/.dockerignore` also lists `.env`, `config/jwt/*.pem` and the tests, so that a copy made wider one day does not put a secret in an image.

**The configuration is not in the image.** The container is compiled in the build with the defaults of `.env.example`, which hold no secret, and `.env` is left empty. The real values (`APP_SECRET`, `DATABASE_URL`, the JWT passphrase and keys, the Mercure keys, the ERP settings) come from the environment of the container, as ADR 0008 asked. Symfony needs a `.env` file to exist, and an empty one is enough.

**What the environment must give.** The container does not start without the Mercure keys: the Caddy of the API reads them when it loads its configuration and refuses to run with none. This was seen by starting the image with only `APP_SECRET` and `DATABASE_URL`. Another one, `DEFAULT_URI`, was found the same way: the application answers 500 until it is set. With all of the variables below, the image answers `/health` with a 503 and the JSON of the checks that failed when there is no database, and CI starts it that way. The list comes from `.env.example`, which shows what the application reads:

| Variable | Needed for |
|---|---|
| `APP_SECRET` | Symfony |
| `DATABASE_URL`, `REDIS_URL`, `MESSENGER_TRANSPORT_DSN`, `MESSENGER_FAILED_TRANSPORT_DSN` | PostgreSQL, Redis and the queues |
| `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE` | Signing the access tokens. The first two are paths of files |
| `MERCURE_URL`, `MERCURE_PUBLISHER_JWT_KEY`, `MERCURE_SUBSCRIBER_JWT_KEY` | The hub inside the API. The two keys are required to start |
| `DEFAULT_URI` | Links made outside a request |
| `ERP_*`, `PUTAWAY_STRATEGY`, `GS1_COMPANY_PREFIX` | They have defaults. Set them for a real deployment |

**Settings of production.** `APP_ENV=prod` and `APP_DEBUG=0`. `opcache.validate_timestamps=0`, because the code of an image does not change: a new version is a new container. `SERVER_NAME=:80`, because the TLS is ended in front of the container, and with the default Caddy would try to get a certificate for `localhost`.

**One image for the API, the worker and the scheduler.** As in development, they differ only in the command, so a release is one image.

**The frontend image has two more stages.** `frontend_build` runs `npm ci` and `npm run build`. `frontend_prod` is `caddy:2-alpine` with the built files. The Caddyfile does four things:

- it serves the app, and a route of the app such as `/orders` answers `index.html`;
- it sends `/api/*` and `/health` to the API, and the Mercure hub with the connection unbuffered, at the address in `API_UPSTREAM`, so the browser still talks to one origin;
- it caches the hashed files of `/assets/` for a year and tells the browser to check everything else each time, `index.html` and the service worker included;
- it listens on `PORT` (80 by default) and does not do TLS.

Nothing about the environment is built into the frontend, since it calls the API with relative paths.

**CI builds both images** and checks them (a job `Images`): the backend image has the PHP extensions the application needs, no tests, no JWT keys and an empty `.env`, a compiled container, and it starts from its environment alone; the frontend image answers on `/` and on a route of the app, and serves the hashed files with their cache header. `make images` builds the same two images on a developer's machine.

## Consequences

- The frontend image is 94 MB, against 244 MB of the development one. The backend image is 983 MB, against 962 MB of the development one. They are not comparable: development keeps `vendor/` in a volume and the production image holds it. Most of the weight is FrankenPHP's own image. An Alpine variant would be smaller, and it was not tried.
- The image does not migrate the database. The migrations are in it, and the deployment runs them (E10-03).
- The JWT key pair is not in the image. The deployment gives the two paths as files or secrets (E10-02).
- Both images run as root. Running as a user without privileges needs changes to the entrypoint, which writes ACLs under `var/`, and to Caddy's directories. It goes with the security work of E10-05, with the security headers and a content security policy, which the frontend server does not set yet.
- The images have no `HEALTHCHECK`. The platform probes `/health` for the API and `/` for the frontend.
- PHP's preload (`opcache.preload`) was not used. It would shorten the first requests a little and has not been measured.
- The images are not scanned for vulnerabilities yet. Dependabot and its checks are E10-05.
- The demo data (`make demo`) was meant for development. Whether the public demo of E10-03 can load it in a production image is for that task to settle.
