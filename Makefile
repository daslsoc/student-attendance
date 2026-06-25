# Student Attendance development tasks.
#
# All PHP tooling runs inside the `app` container (PHP 8.2 + Xdebug); there is
# no PHP/Composer on the host. JavaScript (Vitest) runs on the host (Node),
# since the PHP image carries no Node. Dusk drives a Selenium container via the
# docker-compose.dusk.yml override.
#
# Production is a shared server (see docs/deployment.md) — none of this Docker
# tooling runs in prod. Run `make` (no target) or `make help` to see targets.

.DEFAULT_GOAL := help

DC       := docker compose
DC_DUSK  := docker compose -f docker-compose.yml -f docker-compose.dusk.yml
# Plain app one-off (used for composer/artisan/tests). Xdebug off keeps it snappy.
APP      := $(DC) run --rm -e XDEBUG_MODE=off app
# Coverage needs the Xdebug coverage driver.
APP_COV  := $(DC) run --rm -e XDEBUG_MODE=coverage app

.PHONY: help
help: ## Show available targets
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

## --- one-time setup ---

.PHONY: build
build: ## Rebuild every image (run once after pulling)
	$(DC) build

.PHONY: install
install: ## Install PHP (in container) + JS (host) dependencies, with dev deps
	$(APP) composer install --no-interaction
	npm install

.PHONY: db-up
db-up: ## Bring up MySQL and wait until it accepts connections
	@$(DC) up -d db >/dev/null
	@echo "Waiting for MySQL..."
	@for i in $$(seq 1 30); do \
		$(DC) exec -T db sh -c 'mysql -uroot -psecret -e "SELECT 1" >/dev/null 2>&1' && exit 0; \
		sleep 1; \
	done; \
	echo "MySQL did not become ready" >&2; exit 1

.PHONY: db-setup
db-setup: db-up ## Create + migrate the attendance_test and attendance_dusk databases
	$(DC) exec -T db sh -c 'mysql -uroot -psecret -e "\
		CREATE DATABASE IF NOT EXISTS attendance_test; \
		CREATE DATABASE IF NOT EXISTS attendance_dusk; \
		GRANT ALL PRIVILEGES ON attendance_test.* TO \"laravel_user\"@\"%\"; \
		GRANT ALL PRIVILEGES ON attendance_dusk.* TO \"laravel_user\"@\"%\"; \
		FLUSH PRIVILEGES;"'
	$(APP) sh -c 'DB_CONNECTION=mysql DB_HOST=db DB_PORT=3306 DB_DATABASE=attendance_test DB_USERNAME=root DB_PASSWORD=secret php artisan migrate --force'
	$(APP) sh -c 'DB_CONNECTION=mysql DB_HOST=db DB_PORT=3306 DB_DATABASE=attendance_dusk DB_USERNAME=root DB_PASSWORD=secret php artisan migrate --force'

## --- tests ---

.PHONY: test
test: db-up ## Run Unit + Feature (PHPUnit) against attendance_test
	$(APP) composer test

.PHONY: test-unit
test-unit: db-up ## Run only the Unit suite
	$(APP) vendor/bin/phpunit --testsuite Unit

.PHONY: test-feature
test-feature: db-up ## Run only the Feature suite
	$(APP) vendor/bin/phpunit --testsuite Feature

.PHONY: test-dusk
test-dusk: dusk-up ## Run Dusk (browser) tests against attendance_dusk, then restore
	$(DC_DUSK) run --rm app php artisan dusk; status=$$?; $(MAKE) dusk-down; exit $$status

.PHONY: test-all
test-all: test test-dusk ## Run PHPUnit + Dusk back-to-back

.PHONY: coverage
coverage: db-up ## Run PHPUnit with HTML coverage. Opens tests/coverage/index.html.
	$(APP_COV) vendor/bin/phpunit --coverage-html tests/coverage
	@echo ""
	@echo "Coverage report: file://$(PWD)/tests/coverage/index.html"

.PHONY: coverage-all
coverage-all: coverage js-coverage ## Run both PHPUnit and Vitest coverage.

.PHONY: js-test
js-test: ## Run JS (Vitest) tests against tests/js/
	npm run test

.PHONY: js-test-watch
js-test-watch: ## Run Vitest in watch mode
	npm run test:watch

.PHONY: js-coverage
js-coverage: ## Run Vitest with v8 HTML coverage. Opens tests/js-coverage/index.html.
	npm run test:coverage
	@echo ""
	@echo "JS coverage report: file://$(PWD)/tests/js-coverage/index.html"

## --- lint / format ---

.PHONY: lint
lint: ## Check PHP style (Laravel Pint, laravel preset). Read-only — fails on diffs.
	$(APP) vendor/bin/pint --test

.PHONY: lint-fix
lint-fix: ## Apply Laravel Pint fixes to PHP files
	$(APP) vendor/bin/pint

## --- dusk stack control ---

.PHONY: dusk-up
dusk-up: ## Bring up app/nginx/db/selenium with the Dusk DB override
	$(DC_DUSK) up -d db nginx app selenium
	@# php-fpm serves as www-data, but the mounted storage is host-owned —
	@# make it writable so file sessions/cache/compiled views work.
	$(DC_DUSK) exec -T -u root app chown -R www-data:www-data storage bootstrap/cache

.PHONY: dusk-down
dusk-down: ## Restore app to the normal dev DB (after a Dusk run)
	$(DC) up -d app

## --- shortcuts ---

.PHONY: dusk
dusk: ## Pass args to dusk, e.g. `make dusk ARGS="tests/Browser/LoginTest.php"` (needs dusk-up)
	$(DC_DUSK) run --rm app php artisan dusk $(ARGS)

.PHONY: phpunit
phpunit: db-up ## Pass arbitrary args to phpunit, e.g. `make phpunit ARGS="--filter test_login"`
	$(APP) vendor/bin/phpunit $(ARGS)

.PHONY: artisan
artisan: ## Run artisan, e.g. `make artisan ARGS="migrate:status"`
	$(APP) php artisan $(ARGS)

.PHONY: composer
composer: ## Run composer, e.g. `make composer ARGS="require foo/bar"`
	$(APP) composer $(ARGS)

.PHONY: npm
npm: ## Run npm on the host, e.g. `make npm ARGS="install"`
	npm $(ARGS)

.PHONY: assets
assets: ## Build production JS/CSS assets (Vite)
	npm run build

.PHONY: shell
shell: ## Open a shell in the app container
	$(APP) sh

## --- stack control ---

.PHONY: up
up: ## Bring up the dev stack (app + db + nginx)
	$(DC) up -d

.PHONY: down
down: ## Stop everything
	$(DC) down

.PHONY: ps
ps: ## Show service status
	$(DC) ps

.PHONY: logs
logs: ## Tail logs (all services, or pass SERVICE=app for one)
	$(DC) logs -f $(SERVICE)

.PHONY: mysql
mysql: ## Open mysql client. Defaults to attendance_db; override with DB=attendance_test
	$(DC) exec db sh -c 'mysql -uroot -psecret $(or $(DB),attendance_db)'
