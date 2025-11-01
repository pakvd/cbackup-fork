#!/bin/bash
set -e

echo "=== Docker Entrypoint Script Starting ==="
echo "Current directory: $(pwd)"
echo "Checking /var/www/html contents..."
ls -la /var/www/html/ | head -20

# If vendor directory doesn't exist and composer.json exists, install dependencies
if [ ! -d "/var/www/html/vendor" ]; then
    if [ -f "/var/www/html/composer.json" ]; then
        echo "=== Vendor directory not found. Installing Composer dependencies... ==="
        cd /var/www/html
        echo "Current directory: $(pwd)"
        
        # Use full path to composer
        COMPOSER_CMD="/usr/bin/composer"
        if [ ! -f "$COMPOSER_CMD" ]; then
            COMPOSER_CMD="composer"
        fi
        
        echo "Composer location: $COMPOSER_CMD"
        echo "Composer version: $($COMPOSER_CMD --version 2>&1 || echo 'composer not found')"
        
        # Check composer.json exists
        if [ ! -f "/var/www/html/composer.json" ]; then
            echo "ERROR: composer.json not found!"
            exit 1
        fi
        
        echo "Installing dependencies..."
        # Install dependencies with error handling
        if ! $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-scripts 2>&1; then
            echo "ERROR: Composer install failed!"
            exit 1
        fi
        
        # Verify installation
        if [ -d "/var/www/html/vendor" ]; then
            echo "=== Composer dependencies installed successfully ==="
            echo "Vendor directory size: $(du -sh /var/www/html/vendor | cut -f1)"
        else
            echo "=== ERROR: Vendor directory still not found after installation ==="
            exit 1
        fi
    else
        echo "=== WARNING: Vendor directory not found and composer.json is missing! ==="
        echo "Files in /var/www/html:"
        ls -la /var/www/html/ | head -10
    fi
else
    echo "=== Vendor directory already exists ==="
fi

# Create necessary directories if they don't exist
mkdir -p /var/www/html/runtime
mkdir -p /var/www/html/runtime/sessions
mkdir -p /var/www/html/web/assets
mkdir -p /var/www/html/logger

# Set proper permissions
chown -R www-data:www-data /var/www/html/runtime
chown -R www-data:www-data /var/www/html/web/assets
chmod -R 775 /var/www/html/runtime
chmod -R 775 /var/www/html/web/assets

# Execute the original command
exec "$@"

