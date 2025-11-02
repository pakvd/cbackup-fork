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
            echo "Running: $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs"
            
            # Capture both stdout and stderr
            # Use --ignore-platform-reqs to ignore missing ext-mcrypt (deprecated in PHP 7.2+)
            # Use --no-scripts to avoid running scripts that might fail
            if OUTPUT=$($COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs 2>&1); then
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
                
                # Try composer update if install failed due to lock file issues
                if echo "$OUTPUT" | grep -q "composer update" || echo "$OUTPUT" | grep -q "compatible set of packages"; then
                    echo "=== Trying composer update to fix lock file issues ==="
                    echo "Running: $COMPOSER_CMD update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs"
                    
                    if UPDATE_OUTPUT=$($COMPOSER_CMD update --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs 2>&1); then
                        echo "Composer update completed successfully"
                        echo "$UPDATE_OUTPUT" | tail -30
                        
                        # Verify installation
                        if [ -d "/var/www/html/vendor" ]; then
                            echo "=== Composer dependencies installed successfully after update ==="
                            echo "Vendor directory size: $(du -sh /var/www/html/vendor 2>/dev/null | cut -f1 || echo 'unknown')"
                        else
                            echo "=== WARNING: Composer update completed but vendor directory not found ==="
                        fi
                    else
                        echo "=== ERROR: Composer update also failed ==="
                        echo "Update output:"
                        echo "$UPDATE_OUTPUT"
                        echo "=== End of Composer update error output ==="
                        echo "The application may not work until dependencies are installed."
                    fi
                else
                    echo "The application may not work until dependencies are installed."
                fi
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

# Set proper permissions for directories and files
# Note: For volume mounts, we can't change ownership, so we set permissions for group write
chmod -R 775 /var/www/html/runtime
chmod -R 775 /var/www/html/web/assets
chmod -R 775 /var/www/html/logger
chmod 775 /var/www/html || true  # Allow write to root directory for install.lock

# Try to change ownership if possible (may fail with volume mounts, but try anyway)
chown -R www-data:www-data /var/www/html/runtime 2>/dev/null || true
chown -R www-data:www-data /var/www/html/web/assets 2>/dev/null || true
chown -R www-data:www-data /var/www/html/logger 2>/dev/null || true

# Ensure install.lock can be created (if directory is writable)
if [ -w "/var/www/html" ]; then
    echo "=== /var/www/html is writable ==="
else
    echo "=== WARNING: /var/www/html is not writable, install.lock creation may fail ==="
    # Try to make it writable
    chmod 775 /var/www/html 2>/dev/null || true
fi

# Update PHP-FPM pool configuration if not already set
# This ensures settings are applied even without rebuilding the image
if [ -f "/usr/local/etc/php-fpm.d/www.conf" ]; then
    echo "=== Updating PHP-FPM pool configuration ==="
    # Update pm.max_children if it's still 5 or less
    if grep -q "^pm.max_children = 5$" /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || \
       grep -q "^pm.max_children =[[:space:]]*[0-5]$" /usr/local/etc/php-fpm.d/www.conf 2>/dev/null; then
        sed -i 's/^pm.max_children =.*/pm.max_children = 20/' /usr/local/etc/php-fpm.d/www.conf
        echo "Updated pm.max_children to 20"
    fi
    # Update other settings
    sed -i 's/^pm.start_servers =.*/pm.start_servers = 5/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
    sed -i 's/^pm.min_spare_servers =.*/pm.min_spare_servers = 3/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
    sed -i 's/^pm.max_spare_servers =.*/pm.max_spare_servers = 10/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
    sed -i 's/^request_terminate_timeout =.*/request_terminate_timeout = 60s/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
fi

# Always ensure we can start PHP-FPM
echo "=== Starting PHP-FPM ==="
echo "Command to execute: $@"

# Execute the original command (should be php-fpm)
exec "$@"

