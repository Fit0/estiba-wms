# Estiba WMS developer commands. Run `make` to list them.

DC  := docker compose
PHP := $(DC) exec -T php

.DEFAULT_GOAL := help
.PHONY: help env up down build logs sh composer console jwt-keys tenant demo stock-rebuild worker-logs worker-restart failed retry-failed validate test test-unit stan cs fix rector qa

help: ## List available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[33m%-10s\033[0m %s\n", $$1, $$2}'

## —— Environment ————————————————————————————————————————————
env: ## Create backend/.env from .env.example with new secrets (leaves an existing one alone)
	@sh backend/scripts/create-env.sh

up: env ## Start the stack and wait until it is healthy
	$(DC) up -d --wait

down: ## Stop the stack
	$(DC) down --remove-orphans

build: ## Rebuild the Docker images
	$(DC) build --pull

logs: ## Follow PHP logs
	$(DC) logs -f php

sh: ## Open a shell in the PHP container
	$(DC) exec php sh

composer: ## Run Composer, e.g. make composer c="require foo/bar"
	$(PHP) composer $(c)

console: ## Run the Symfony console, e.g. make console c=about
	$(PHP) bin/console $(c)

jwt-keys: ## Generate the JWT key pair (kept out of git). Done on first start in development.
	$(PHP) bin/console lexik:jwt:generate-keypair --skip-if-exists

tenant: ## Create a tenant and its admin, e.g. make tenant name="Acme" email=admin@acme.test (asks for the password)
	$(DC) exec php bin/console app:tenant:create "$(name)" "$(email)"

demo: ## Load the fashion demo (a company, a distribution centre and a collection). Asks for a password.
	$(DC) exec php bin/console app:demo:load

stock-rebuild: ## Build the stock read model again from the ledger (ADR 0013)
	$(PHP) bin/console app:stock-overview:rebuild

## —— Worker ————————————————————————————————————————————————
worker-logs: ## Follow the worker that handles the asynchronous events
	$(DC) logs -f worker

worker-restart: ## Restart the worker, so it runs the code you just changed
	$(DC) restart worker

failed: ## List the events that failed every retry. Show one with: make failed id=<id>
	$(PHP) bin/console messenger:failed:show $(id) -vv

retry-failed: ## Handle the failed events again, asking for each one
	$(DC) exec php bin/console messenger:failed:retry

## —— Quality ————————————————————————————————————————————————
validate: ## Validate composer.json and the lock file, and audit dependencies
	$(PHP) composer validate --strict
	$(PHP) composer audit

test: ## Run the PHPUnit test suite
	$(PHP) bin/console doctrine:database:create --if-not-exists --env=test --quiet
	$(PHP) bin/phpunit $(c)

test-unit: ## Run only the unit tests (no database, about a second)
	$(PHP) bin/phpunit tests/Unit

stan: ## Run PHPStan static analysis
	$(PHP) bin/console cache:warmup --env=dev --quiet
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Check code style without changing files
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

rector: ## Show Rector refactorings without applying them
	$(PHP) vendor/bin/rector process --dry-run

fix: ## Apply Rector refactorings and fix code style
	$(PHP) vendor/bin/rector process
	$(PHP) vendor/bin/php-cs-fixer fix

qa: validate cs rector stan test ## Run every check (the same ones CI runs)
