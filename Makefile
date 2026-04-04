.DEFAULT_GOAL := help

DOCKER_IMAGE  ?= mougrim/yaml-cst-dev
DOCKER_BUILD_TARGET ?= runtime
DOCKER_RUN     = docker run --rm -v "$(CURDIR):/app" -w /app $(DOCKER_IMAGE)

##@ Help

.PHONY: help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) }' $(MAKEFILE_LIST)

##@ Docker

.PHONY: build
build: ## Build the Docker image (compiles tree-sitter .so files)
	docker build --target $(DOCKER_BUILD_TARGET) -t $(DOCKER_IMAGE) .

##@ Dependencies

.PHONY: install
install: ## Install Composer dependencies (runs inside Docker)
	$(DOCKER_RUN) composer install --no-interaction --prefer-dist

##@ Code Quality

.PHONY: test
test: ## Run PHPUnit tests (runs inside Docker)
	$(DOCKER_RUN) vendor/bin/phpunit

.PHONY: test-unit
test-unit: ## Run only unit tests (runs inside Docker)
	$(DOCKER_RUN) vendor/bin/phpunit --testsuite Unit

.PHONY: test-integration
test-integration: ## Run only integration tests (runs inside Docker)
	$(DOCKER_RUN) vendor/bin/phpunit --testsuite Integration

.PHONY: test-coverage
test-coverage: ## Run PHPUnit with coverage report (Docker, requires pcov — enabled in the image)
	$(DOCKER_RUN) vendor/bin/phpunit --coverage-text --coverage-html build/coverage

.PHONY: phpstan
phpstan: ## Run PHPStan static analysis (runs inside Docker)
	$(DOCKER_RUN) vendor/bin/phpstan analyse --no-progress

.PHONY: cs-check
cs-check: ## Check code style with php-cs-fixer (runs inside Docker)
	$(DOCKER_RUN) vendor/bin/php-cs-fixer fix --dry-run --diff

.PHONY: cs-fix
cs-fix: ## Fix code style with php-cs-fixer (runs inside Docker)
	$(DOCKER_RUN) vendor/bin/php-cs-fixer fix

##@ Shell

.PHONY: bash
bash: ## Open a bash shell inside the Docker container
	docker run --rm -it -v "$(CURDIR):/app" -w /app $(DOCKER_IMAGE) bash

##@ Maintenance

.PHONY: clean
clean: ## Remove local build artifacts (vendor, caches)
	rm -rf vendor/ .phpunit.cache/ .phpunit.result.cache .php-cs-fixer.cache

##@ CI

.PHONY: ci
ci: build install test phpstan cs-check ## Run full CI pipeline (build → install → test → phpstan → cs-check)
