DC      = docker compose
PHP     = $(DC) exec php
CONSOLE = $(PHP) php bin/console

.DEFAULT_GOAL := help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

## —— Getting started ——————————————————————————————————————————
install: up vendor db ## Build, install dependencies and set up the database
	@printf "\n✓ Ready → http://localhost:%s\n" "$$(grep -E '^HTTP_PORT=' .env | cut -d= -f2)"

up: ## Start the stack
	$(DC) up -d --build --wait

down: ## Stop the stack
	$(DC) down

vendor: ## Install Composer dependencies
	$(PHP) composer install --no-interaction --prefer-dist

db: ## Create the database and run migrations
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

demo: ## Make an episode from recorded API responses (no API key needed)
	$(DC) exec -e CASSETTE=fixtures/cassettes/worlds-2026.json php php bin/console app:episode fixtures/briefs/worlds-2026.json
	@printf "\n✓ Listen → http://localhost:%s\n" "$$(grep -E '^HTTP_PORT=' .env | cut -d= -f2)"

## —— Dev ————————————————————————————————————————————————————
sh: ## Shell in the PHP container
	$(PHP) bash

logs: ## Follow logs
	$(DC) logs -f

console: ## Run a console command: make console c="debug:router"
	$(CONSOLE) $(c)

ollama-pull: ## Pull a local model: make ollama-pull m=llama3.2
	$(DC) --profile ollama up -d ollama
	$(DC) exec ollama ollama pull $(m)

## —— Quality ————————————————————————————————————————————————
qa: cs stan test ## Run everything CI runs

test: ## Unit + functional tests (no browser)
	$(PHP) vendor/bin/phpunit --exclude-group e2e

e2e: ## Browser tests with Panther
	$(PHP) vendor/bin/phpunit --group e2e

stan: ## PHPStan (level max)
	$(CONSOLE) cache:warmup --env=dev >/dev/null
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Check coding standards
	$(PHP) vendor/bin/php-cs-fixer check --diff

cs-fix: ## Fix coding standards
	$(PHP) vendor/bin/php-cs-fixer fix

.PHONY: help install up down vendor db demo sh logs console ollama-pull qa test e2e stan cs cs-fix
