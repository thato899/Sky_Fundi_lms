SHELL := /bin/bash
COMPOSE := docker compose
APP := $(COMPOSE) exec -T app

.PHONY: build init up down restart migrate migrate-check seed test test-learners pint analyse verify status logs shell health deployment-validate

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
