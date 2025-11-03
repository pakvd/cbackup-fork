.PHONY: help up down restart logs permissions set-permissions

help: ## Show this help message
	@echo "cBackup Docker Compose Commands"
	@echo ""
	@echo "Available commands:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

set-permissions: ## Set correct file permissions (run this first)
	@echo "Setting file permissions..."
	@if ./set-permissions.sh 2>/dev/null; then \
		echo "✓ Permissions set successfully"; \
	elif sudo ./set-permissions.sh 2>/dev/null; then \
		echo "✓ Permissions set successfully (with sudo)"; \
	else \
		echo "⚠️  Could not set all permissions. Some files may require manual permission changes."; \
		./set-permissions.sh; \
	fi

up: set-permissions ## Start containers (automatically sets permissions and installs dependencies)
	@echo "Starting containers..."
	@docker compose up -d
	@echo "Waiting for containers to start..."
	@sleep 5
	@echo "Checking Composer dependencies..."
	@sleep 3
	@if ! docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then \
		echo "Installing Composer dependencies..."; \
		echo "Attempting composer update (lock file may be outdated)..."; \
		if docker compose exec -T web composer update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs 2>&1 | tail -15; then \
			if docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then \
				echo "✓ Composer dependencies installed via update"; \
			else \
				echo "⚠️  Composer update completed but vendor/autoload.php not found. Check logs: docker compose logs web"; \
			fi \
		else \
			echo "⚠️  Composer update failed. Trying install..."; \
			docker compose exec -T web composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs 2>&1 | tail -10 || true; \
			if docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then \
				echo "✓ Composer dependencies installed via install"; \
			else \
				echo "⚠️  Composer dependencies installation may have failed. Check logs: docker compose logs web"; \
			fi \
		fi \
	else \
		echo "✓ Composer dependencies already installed"; \
	fi

down: ## Stop containers
	@docker compose down

restart: ## Restart containers
	@docker compose restart

logs: ## Show logs
	@docker compose logs -f

build: ## Build containers
	@docker compose build

rebuild: ## Rebuild and restart containers
	@docker compose build --no-cache
	@$(MAKE) up

clean: ## Stop containers and remove volumes (⚠️  deletes data)
	@echo "⚠️  This will delete all data!"
	@read -p "Are you sure? [y/N] " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker compose down -v; \
		echo "✓ Containers and volumes removed"; \
	fi

status: ## Show container status
	@docker compose ps

shell-web: ## Open shell in web container
	@docker compose exec web /bin/bash

shell-db: ## Open shell in database container
	@docker compose exec db /bin/bash

install: ## Run installation wizard (opens in browser)
	@echo "Open http://localhost:8080 in your browser"

install-composer: ## Install Composer dependencies manually
	@echo "Installing Composer dependencies..."
	@docker compose exec web composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs || \
	docker compose exec web composer update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs

check-deps: ## Check if Composer dependencies are installed
	@if docker compose exec -T web test -f /var/www/html/vendor/autoload.php 2>/dev/null; then \
		echo "✓ Composer dependencies are installed"; \
	else \
		echo "✗ Composer dependencies are NOT installed"; \
		echo "Run: make install-composer"; \
		exit 1; \
	fi

