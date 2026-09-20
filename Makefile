.PHONY: dev prod prod-down prod-logs prod-shell

# WWWUSER/WWWGROUP are set by vendor/bin/sail, not by docker compose: exporting
# them here keeps the `prod` targets from warning about unset variables.
COMPOSE_PROD := WWWUSER=$(shell id -u) WWWGROUP=$(shell id -g) docker compose --profile production

dev:
	vendor/bin/sail up -d --wait
	npx concurrently -n "sail,queue,reverb" -c "red,green,blue" "vendor/bin/sail logs -f" "vendor/bin/sail artisan queue:work" "vendor/bin/sail artisan reverb:start"

prod:
	@test -f .env.production || { \
		echo "missing .env.production — run: cp .env.production.example .env.production"; \
		echo "then fill in APP_KEY (artisan key:generate --show) and the dashboard passwords"; \
		exit 1; \
	}
	$(COMPOSE_PROD) up -d --build --wait production

prod-logs:
	$(COMPOSE_PROD) logs -f production

prod-shell:
	$(COMPOSE_PROD) exec -u www-data production bash

prod-down:
	$(COMPOSE_PROD) stop production
	$(COMPOSE_PROD) rm -f production
