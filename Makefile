# Estiba WMS developer commands. Run `make` to list them.

DC  := docker compose
PHP := $(DC) exec -T php

.DEFAULT_GOAL := help
.PHONY: help up down build logs sh composer console test test-unit stan cs fix rector qa

help: ## List available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[33m%-10s\033[0m %s\n", $$1, $$2}'

## —— Environment ————————————————————————————————————————————
up: ## Start the stack and wait until it is healthy
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

## —— Quality ————————————————————————————————————————————————
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

qa: cs rector stan test ## Run every check (the same ones CI runs)
