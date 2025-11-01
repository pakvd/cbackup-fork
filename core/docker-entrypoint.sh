#!/bin/bash
# Don't use set -e here - we want to continue even if composer install fails
# The app will show a proper error message if vendor is missing

echo "=== Docker Entrypoint Script Starting ==="
echo "Current directory: $(pwd)"

# If vendor directory doesn't exist and composer.json exists, install dependencies
if [ ! -d "/var/www/html/vendor" ]; then
    if [ -f "/var/www/html/composer.json" ]; then
        echo "=== Vendor directory not found. Installing Composer dependencies... ==="
        cd /var/www/html
        
        # Use full path to composer
        COMPOSER_CMD="/usr/bin/composer"
        if [ ! -f "$COMPOSER_CMD" ]; then
            COMPOSER_CMD="composer"
        fi
        
        echo "Composer location: $COMPOSER_CMD"
        
        # Install dependencies - don't fail if it errors, just log it
        echo "Installing dependencies..."
        if $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-scripts 2>&1; then
            # Verify installation
            if [ -d "/var/www/html/vendor" ]; then
                echo "=== Composer dependencies installed successfully ==="
            else
                echo "=== WARNING: Composer completed but vendor directory not found ==="
            fi
        else
            echo "=== WARNING: Composer install failed, but continuing... ==="
            echo "The application may not work until dependencies are installed."
        fi
    else
        echo "=== WARNING: Vendor directory not found and composer.json is missing! ==="
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

# Always ensure we can start PHP-FPM
echo "=== Starting PHP-FPM ==="
echo "Command to execute: $@"

# Execute the original command (should be php-fpm)
exec "$@"

