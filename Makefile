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

up: set-permissions ## Start containers (automatically sets permissions)
	@echo "Starting containers..."
	@docker compose up -d

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

