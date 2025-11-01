#!/bin/bash
set -e

# If vendor directory doesn't exist and composer.json exists, install dependencies
if [ ! -d "/var/www/html/vendor" ] && [ -f "/var/www/html/composer.json" ]; then
    echo "Vendor directory not found. Installing Composer dependencies..."
    cd /var/www/html
    composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
    echo "Composer dependencies installed successfully."
elif [ ! -d "/var/www/html/vendor" ]; then
    echo "Warning: Vendor directory not found and composer.json is missing!"
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

