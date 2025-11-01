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
        
        # Check if composer is executable
        if [ ! -x "$COMPOSER_CMD" ]; then
            echo "ERROR: Composer is not executable at $COMPOSER_CMD"
            # Try to find composer in PATH
            COMPOSER_CMD=$(which composer 2>/dev/null || echo "composer")
            echo "Trying composer from PATH: $COMPOSER_CMD"
        fi
        
        # Test composer
        if ! $COMPOSER_CMD --version 2>&1; then
            echo "ERROR: Composer is not working. Cannot install dependencies."
        else
            echo "Composer version check passed"
            
            # Check composer.json
            if [ -f "/var/www/html/composer.json" ]; then
                echo "composer.json found"
            else
                echo "ERROR: composer.json not found at /var/www/html/composer.json"
            fi
            
            # Check if composer.lock exists
            if [ -f "/var/www/html/composer.lock" ]; then
                echo "composer.lock found - using locked versions"
            else
                echo "WARNING: composer.lock not found - will resolve dependencies"
            fi
            
            # Install dependencies - capture output and error
            echo "Installing dependencies..."
            echo "Running: $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-scripts"
            
            # Capture both stdout and stderr
            if OUTPUT=$($COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-scripts 2>&1); then
                echo "Composer command completed with exit code 0"
                echo "$OUTPUT" | tail -20
                
                # Verify installation
                if [ -d "/var/www/html/vendor" ]; then
                    echo "=== Composer dependencies installed successfully ==="
                    echo "Vendor directory size: $(du -sh /var/www/html/vendor 2>/dev/null | cut -f1 || echo 'unknown')"
                else
                    echo "=== WARNING: Composer completed but vendor directory not found ==="
                fi
            else
                EXIT_CODE=$?
                echo "=== ERROR: Composer install failed with exit code $EXIT_CODE ==="
                echo "Composer output:"
                echo "$OUTPUT"
                echo "=== End of Composer error output ==="
                echo "The application may not work until dependencies are installed."
            fi
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

