# 0021. The frontend project: Vite in Docker, behind a proxy to the API

- Status: Accepted
- Date: 2026-10-25

## Context

The back-office app and the picking PWA need a project that every developer, and the CI, build and check the same way. The backend already runs in Docker (`make up`) and its checks are the ones CI runs (`make qa`). The frontend has to follow. Several things had to be decided:

- **Where Node runs.** On the developer's machine or in Docker.
- **How the browser reaches the API**: a different origin (port 5173 and port 8080), or one.
- **How the dependencies are installed** in a container whose source is a bind mount.
- **Which checks guard the code**, and how strict they are, since TypeScript is as easy to loosen as PHP.
- **Which versions**, when a plugin lags behind its tool.

## Options considered

1. **Node on the host.** Fast, and it breaks the rule that nothing but Docker is needed. Two developers get two Node versions.
2. **Node in Docker**, with the source mounted and `node_modules` in a volume (the way `vendor/` is).
3. **The browser calls the API at its own address** (`http://localhost:8080`), which needs CORS on the backend: a bundle, a list of origins to keep in step with every environment, and preflight requests on every call.
4. **The dev server forwards `/api` to the backend**, so the browser sees one origin. In production the same holds behind any reverse proxy, and the code never knows the address of the API.
5. **`npm ci` at every start.** Always right, and slow: it wipes `node_modules`.
6. **Install when `package-lock.json` changes**, remembered with a hash inside the volume.

## Decision

- **Node 24 (LTS) in Docker** (options 2, 4 and 6). A `frontend` service in `compose.yaml` runs the Vite development server on port 5173. `make up` starts it with the rest. `node_modules` lives in a volume, and `docker-entrypoint.sh` runs `npm ci` only when the hash of `package-lock.json` is not the one it saved. The files of the project are called through `sh`, not executed, as on Windows the executable bit of a script is not reliable.
- **One origin.** Vite forwards `/api` and `/health` to the PHP service (`API_PROXY_TARGET`). The backend needs no CORS and the app uses relative paths. Adding CORS later, for a client that really is elsewhere, is a decision of its own.
- **Polling for changes.** A bind mount from Windows or macOS does not tell the container that a file changed, so hot reload needs the server to look for them (`VITE_USE_POLLING`, on by default, off where it is not needed).
- **npm.** It comes with Node and needs nothing more. `package-lock.json` is committed and installed with `npm ci` everywhere.
- **The checks are strict from the first line** and run the same way in `make front-qa` and in the `Frontend` job of CI:
  - **TypeScript** with `strict`, `noUncheckedIndexedAccess` and `exactOptionalPropertyTypes`.
  - **ESLint** with `typescript-eslint` in its strict, type-checked configuration. `any` and the non-null assertion `!` are errors, as are loose comparisons and conditions that are not booleans, the way the PHP code reads. The rules of `react-hooks` catch the mistakes of effects and dependencies, and `jsx-a11y` the first accessibility mistakes (the target is WCAG 2.2 AA).
  - **Prettier** decides the format, and ESLint does not argue with it.
  - **Vitest** with Testing Library, in a jsdom page.
  - **The build** (`tsc -b && vite build`) must pass, and `npm audit` of what ships must have nothing high.
- **ESLint 9 and not 10.** `eslint-plugin-jsx-a11y` does not declare support for ESLint 10 yet, and dropping accessibility rules to follow a version would be the wrong trade. When the plugin supports it, the move is a change of one line.

## Consequences

- A fresh clone starts the frontend with `make up`, and the first start takes a few minutes to install the dependencies.
- A new dependency is added with `make front-npm c="install <package>"`, and the changed `package-lock.json` is committed with it. The dependencies not named in the table of `CLAUDE.md` still need to be asked about first.
- `Frontend` is a sixth job of CI. It has to be added to the checks that a pull request needs in the branch protection of `main`, or it would only be advice.
- There is no production image yet. It arrives with the deployment (E10), and it will serve the built files behind the same kind of proxy.
- The picking PWA, with its service worker, will need HTTPS in the field. The dev server does not provide it, and it is a problem for the PWA task.
