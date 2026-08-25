APP_URL := $(shell sed -n 's,^APP_URL=,,p' .env 2>/dev/null | head -1)
BARE := $(patsubst http://%,%,$(APP_URL))
HOST := $(firstword $(subst :, ,$(BARE)))
HOST := $(if $(HOST),$(HOST),local.takt.de)
PORT ?= $(word 2,$(subst :, ,$(BARE)))
PORT := $(if $(PORT),$(PORT),8000)
URL := http://$(HOST):$(PORT)
AGENT := $(HOME)/Library/LaunchAgents/de.takt.server.plist
OURS := $(shell grep -Fq "$(CURDIR)" "$(AGENT)" 2>/dev/null && echo yes)
PID_FILE := storage/app/takt-serve.pid
LOG_FILE := storage/logs/serve.log

.DEFAULT_GOAL := help
.PHONY: help start stop restart status app autostart autostart-remove setup update

help:
	@echo "Takt"
	@echo "  make start     start the app on $(URL)"
	@echo "  make stop      stop the app"
	@echo "  make restart   stop, then start"
	@echo "  make status    show whether the app is running"
	@echo "  make app       build the macOS app bundle into ~/Applications"
	@echo "  make autostart start the server with your login session"
	@echo "  make setup     make a fresh copy ready to use (name, app, login item)"
	@echo "  make update    pull, install, migrate and rebuild"
	@echo ""
	@echo "Override the port with: make start PORT=8080"

start:
	@if [ -n "$(OURS)" ]; then \
		launchctl kickstart "gui/`id -u`/de.takt.server" >/dev/null 2>&1; \
		echo "Takt runs as a login item — kickstarted it on $(URL)"; \
		echo "Remove it with: make autostart-remove"; \
		exit 0; \
	fi; \
	pid=`cut -d' ' -f1 $(PID_FILE) 2>/dev/null`; \
	port=`cut -d' ' -f2 $(PID_FILE) 2>/dev/null`; \
	if [ -n "$$pid" ] && kill -0 $$pid 2>/dev/null; then \
		echo "Takt is already running on http://$(HOST):$$port (pid $$pid)"; \
		[ "$$port" = "$(PORT)" ] || echo "Run 'make stop' first to switch to port $(PORT)."; \
		exit 0; \
	fi; \
	if [ ! -d vendor ] || [ ! -f public/build/manifest.json ]; then \
		echo "Missing dependencies or built assets — run: composer install && npm install && npm run build"; \
		exit 1; \
	fi; \
	if lsof -ti tcp:$(PORT) >/dev/null 2>&1; then \
		echo "Port $(PORT) is already in use by another process — free it or use: make start PORT=8001"; \
		exit 1; \
	fi; \
	php artisan serve --host=127.0.0.1 --port=$(PORT) >> $(LOG_FILE) 2>&1 & \
	echo "$$! $(PORT)" > $(PID_FILE); \
	sleep 1; \
	if kill -0 `cut -d' ' -f1 $(PID_FILE)` 2>/dev/null; then \
		echo "Takt is running on $(URL) (log: $(LOG_FILE))"; \
	else \
		rm -f $(PID_FILE); \
		echo "Start failed:"; \
		tail -n 3 $(LOG_FILE); \
		exit 1; \
	fi

stop:
	@if [ -n "$(OURS)" ]; then \
		echo "Takt runs as a login item and restarts itself — stop it for good with: make autostart-remove"; \
		exit 0; \
	fi; \
	pid=`cut -d' ' -f1 $(PID_FILE) 2>/dev/null`; \
	if [ -n "$$pid" ] && kill -0 $$pid 2>/dev/null; then \
		pkill -P $$pid 2>/dev/null || true; \
		kill $$pid 2>/dev/null || true; \
		rm -f $(PID_FILE); \
		echo "Takt stopped"; \
	else \
		rm -f $(PID_FILE); \
		echo "Takt is not running"; \
	fi

restart:
	@$(MAKE) --no-print-directory stop
	@$(MAKE) --no-print-directory start

status:
	@if [ -n "$(OURS)" ]; then \
		agent_pid=`launchctl print "gui/\`id -u\`/de.takt.server" 2>/dev/null | awk '/pid = /{print $$3; exit}'`; \
		if [ -n "$$agent_pid" ]; then \
			echo "Takt runs as a login item on $(URL) (pid $$agent_pid)"; \
		else \
			echo "Takt login item is installed but not running — see $(LOG_FILE)"; \
		fi; \
		exit 0; \
	fi; \
	pid=`cut -d' ' -f1 $(PID_FILE) 2>/dev/null`; \
	port=`cut -d' ' -f2 $(PID_FILE) 2>/dev/null`; \
	if [ -n "$$pid" ] && kill -0 $$pid 2>/dev/null; then \
		echo "Takt is running on http://$(HOST):$$port (pid $$pid)"; \
	else \
		echo "Takt is not running"; \
	fi

setup:
	@php artisan takt:setup --port=$(PORT)

app:
	@php artisan takt:app --port=$(PORT)

autostart:
	@php artisan takt:autostart --port=$(PORT)

autostart-remove:
	@php artisan takt:autostart --remove

update:
	@./update.sh
