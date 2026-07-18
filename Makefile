SHELL := /bin/bash
COMPOSE := docker compose
APP := $(COMPOSE) exec -T app

.PHONY: build init up down restart migrate migrate-check seed demo-reset test test-learners pint analyse verify status logs shell health deployment-validate

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart

build:
	$(COMPOSE) build

init:
	$(COMPOSE) up --build init

migrate:
	$(APP) php artisan migrate

migrate-check:
	./scripts/migrate-check.sh

seed:
	$(APP) php artisan db:seed

# LOCAL/DEMO ONLY: destroys the configured database before rebuilding demo data.
demo-reset:
	@environment="$$($(APP) php artisan env --no-ansi)"; [[ "$${environment,,}" != *production* ]] || (echo "Refusing demo reset in production" && exit 1)
	$(APP) php artisan migrate:fresh --seed
	$(APP) php artisan db:seed --class="Database\\Seeders\\HackathonDemoSeeder"

test:
	$(APP) php artisan test

test-learners:
	$(APP) php artisan test modules/Learners/tests

pint:
	$(APP) ./vendor/bin/pint --test

analyse:
	./scripts/analyse.sh

verify:
	./scripts/verify.sh

status:
	./scripts/status.sh

logs:
	$(COMPOSE) logs --tail=200 -f

shell:
	$(COMPOSE) exec app sh

health:
	./scripts/health.sh

deployment-validate:
	./scripts/validate-deployment.sh
