.DEFAULT_GOAL := help

COMPOSE := docker compose --env-file .env.docker --file compose.yaml

.PHONY: help up down stop restart build dev frontend-build ps logs php-shell node-shell redis-cli redis-clear lint lint-php lint-front lint-fix setup-hooks

help:
	@printf '%s\n' \
		'make up           Levantar todos los servicios y esperar a que estén listos.' \
		'make down         Bajar y eliminar los contenedores y la red; conservar los datos.' \
		'make stop         Detener todos los servicios conservando los contenedores.' \
		'make restart      Reiniciar los servicios.' \
		'make build        Construir la imagen de PHP; aplicar luego con make up.' \
		'make dev          Iniciar Vite en el puerto acordado; detener con Ctrl+C.' \
		'make frontend-build Compilar los archivos del frontend en public/build.' \
		'make ps           Ver el estado de los servicios.' \
		'make logs         Seguir los logs de los servicios (Ctrl+C para salir).' \
		'make php-shell    Abrir una consola en el contenedor de PHP.' \
		'make node-shell   Abrir una consola en el contenedor de Node.' \
		'make redis-cli    Abrir la consola de Redis.' \
		'make redis-clear  Borrar todas las claves de todas las bases del Redis de Nuvads.' \
		'make lint         Validar todo el código (PHP y frontend), sin modificarlo.' \
		'make lint-php     Validar solo el código PHP con Pint y phpcs.' \
		'make lint-front   Validar solo el frontend con ESLint.' \
		'make lint-fix     Corregir el formato PHP con Pint y validar el resto con phpcs.' \
		'make setup-hooks  Activar los git hooks versionados del repo (.githooks).'

up:
	$(COMPOSE) up -d --wait

down:
	$(COMPOSE) down --timeout 60

stop:
	$(COMPOSE) stop --timeout 60

restart:
	$(COMPOSE) restart --timeout 60

build:
	$(COMPOSE) build php

dev:
	$(COMPOSE) exec node npm run dev

frontend-build:
	$(COMPOSE) exec -T node npm run build

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs --follow --tail=100

php-shell:
	$(COMPOSE) exec php sh

node-shell:
	$(COMPOSE) exec node sh

redis-cli:
	$(COMPOSE) exec redis redis-cli

redis-clear:
	$(COMPOSE) exec -T redis redis-cli -e FLUSHALL SYNC

lint: lint-php lint-front

lint-php:
	$(COMPOSE) exec -T php ./vendor/bin/pint --test -v
	$(COMPOSE) exec -T php ./vendor/bin/phpcs

lint-front:
	$(COMPOSE) exec -T node npx eslint resources/js

lint-fix:
	$(COMPOSE) exec -T php ./vendor/bin/pint
	$(COMPOSE) exec -T php ./vendor/bin/phpcs
	$(COMPOSE) exec -T node npx eslint --fix resources/js

setup-hooks:
	git config core.hooksPath .githooks
