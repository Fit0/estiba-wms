# Contributing to Estiba WMS

## Workflow

1. Open an issue (feature or bug) describing the change.
2. Create a branch from `main`: `<type>/<issue-number>-<short-description>`, for example `feat/12-location-codes`.
3. Commit using Conventional Commits.
4. Open a pull request that references the issue (`Closes #12`). CI must pass before merging.
5. Squash-merge into `main`. The squash commit message must also follow Conventional Commits.

## Commit messages

Format: `<type>(<scope>): <description>`

| Type | Use for |
|---|---|
| `feat` | A new feature |
| `fix` | A bug fix |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `test` | Adding or fixing tests |
| `docs` | Documentation only |
| `build` | Build system, Docker, dependencies |
| `ci` | CI configuration |
| `perf` | Performance improvement |
| `style` | Formatting, no code change |
| `chore` | Maintenance that fits none of the above |

Scopes are the bounded contexts or app parts: `catalog`, `warehouse`, `inventory`, `inbound`, `outbound`, `api`, `frontend`, `pwa`, `infra`, `docs`.

Examples:

```
feat(warehouse): add LocationCode value object
fix(inventory): reject movements that make stock negative
docs(adr): record decision on multi-tenancy
```

Breaking changes add `!` after the type or scope (`feat(api)!: ...`) and a `BREAKING CHANGE:` footer.

### Local commit check

A `commit-msg` hook validates the format. Enable it once per clone:

```bash
git config core.hooksPath .githooks
```

## Code conventions

- Domain code has no framework dependencies. Doctrine mapping lives in `Infrastructure`.
- Every domain rule comes with a unit test.
- Architecture decisions are recorded as ADRs in `docs/adr`.
- Code, comments and documentation are written in English.
