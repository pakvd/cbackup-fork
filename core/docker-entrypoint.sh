#!/bin/bash
# Don't use set -e here - we want to continue even if composer install fails
# The app will show a proper error message if vendor is missing

echo "=== Docker Entrypoint Script Starting ==="
echo "Running as user: $(whoami)"
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
            echo "Running: $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-plugins --ignore-platform-reqs"
            
            # Capture both stdout and stderr
            # Use --ignore-platform-reqs to ignore missing ext-mcrypt (deprecated in PHP 7.2+)
            # Use --no-scripts to avoid running scripts that might fail
            # Use --no-plugins to avoid plugin compatibility issues with Composer 2.8+
            if OUTPUT=$($COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-plugins --ignore-platform-reqs 2>&1); then
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
                # Also try if vendor directory doesn't exist even if install partially succeeded
                if echo "$OUTPUT" | grep -q "composer update" || echo "$OUTPUT" | grep -q "compatible set of packages" || [ ! -d "/var/www/html/vendor" ]; then
                    echo "=== Trying composer update to fix lock file issues ==="
                    echo "Running: $COMPOSER_CMD update --no-dev --optimize-autoloader --no-interaction --no-scripts --no-plugins --ignore-platform-reqs"
                    
                    if UPDATE_OUTPUT=$($COMPOSER_CMD update --no-dev --optimize-autoloader --no-interaction --no-scripts --no-plugins --ignore-platform-reqs 2>&1); then
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
                    # Even if composer install failed, check if vendor was created (sometimes it partially succeeds)
                    if [ ! -d "/var/www/html/vendor" ]; then
                        echo "=== Trying final composer install without plugins ==="
                        $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-plugins --ignore-platform-reqs 2>&1 | tail -20 || true
                    fi
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

# Ensure CDS content directory has proper permissions
mkdir -p /var/www/html/modules/cds/content
chmod -R 775 /var/www/html/modules/cds/content 2>/dev/null || true

# Ensure bin directory exists
mkdir -p /var/www/html/bin

# Check cbackup.jar - worker container should have copied it already (web depends on worker)
# Worker copies to ./core/bin/cbackup.jar which is mounted as /var/www/html/bin/cbackup.jar
if [ -f "/var/www/html/bin/cbackup.jar" ]; then
    FILE_SIZE=$(stat -c%s /var/www/html/bin/cbackup.jar 2>/dev/null || echo "0")
    if [ "$FILE_SIZE" -gt 1000 ]; then
        echo "✓ cbackup.jar found (size: $FILE_SIZE bytes)"
    else
        echo "⚠ cbackup.jar exists but is empty or too small ($FILE_SIZE bytes)"
        echo "  Worker container might not have copied the file correctly"
    fi
else
    echo "⚠ cbackup.jar not found in /var/www/html/bin/"
    echo "  Worker container should have copied it during startup"
    echo "  You can copy it manually: docker compose cp worker:/app/app.jar core/bin/cbackup.jar"
fi

# Create application.properties if it doesn't exist (from template)
if [ ! -f "/var/www/html/bin/application.properties" ]; then
    echo "=== Creating application.properties from template ==="
    cat > /var/www/html/bin/application.properties << 'EOF'
# SSH Daemon Shell Configuration
sshd.shell.port=8437
sshd.shell.enabled=false
sshd.shell.username=cbadmin
sshd.shell.password=KqPOPh2Lf
sshd.shell.host=localhost
sshd.shell.auth.authType=SIMPLE
sshd.shell.prompt.title=cbackup

# Spring Configuration
spring.main.banner-mode=off

# cBackup Configuration
cbackup.scheme=http
cbackup.site=http://web/index.php
cbackup.token=D0B221B7-B88A-4DF1-8254-76E8766F285B
EOF
    echo "✓ Created application.properties"
fi

# Set correct permissions for bin files
if [ -f "/var/www/html/bin/cbackup.jar" ]; then
    chmod 555 /var/www/html/bin/cbackup.jar 2>/dev/null || true
    echo "✓ Set permissions for cbackup.jar (555)"
fi
if [ -f "/var/www/html/bin/application.properties" ]; then
    chmod 664 /var/www/html/bin/application.properties 2>/dev/null || true
    chmod -x /var/www/html/bin/application.properties 2>/dev/null || true
    echo "✓ Set permissions for application.properties (664)"
fi

# Set correct permissions for yii files
# Note: With volume mounts, permissions may be overridden by host filesystem
# We run as root, so we can try to force permissions even on volume mounts
# yii.bat should be non-writable, non-executable (444 = read-only)
if [ -f "/var/www/html/yii.bat" ]; then
    # Try to set ownership first (we're root, should work)
    chown www-data:www-data /var/www/html/yii.bat 2>/dev/null || true
    
    # Set permissions using multiple methods
    # Method 1: Direct chmod to 444
    chmod 444 /var/www/html/yii.bat 2>/dev/null || true
    
    # Method 2: Remove write and execute bits explicitly
    chmod u-w /var/www/html/yii.bat 2>/dev/null || true
    chmod g-w /var/www/html/yii.bat 2>/dev/null || true
    chmod o-w /var/www/html/yii.bat 2>/dev/null || true
    chmod a-x /var/www/html/yii.bat 2>/dev/null || true
    
    # Method 3: Use numeric mode again after explicit bits
    chmod 444 /var/www/html/yii.bat 2>/dev/null || true
    
    # Verify permissions were set
    PERMS=$(stat -c "%a" /var/www/html/yii.bat 2>/dev/null || echo "unknown")
    IS_WRITABLE=$(test -w /var/www/html/yii.bat && echo "yes" || echo "no")
    IS_EXECUTABLE=$(test -x /var/www/html/yii.bat && echo "yes" || echo "no")
    
    if [ "$PERMS" = "444" ] && [ "$IS_WRITABLE" = "no" ] && [ "$IS_EXECUTABLE" = "no" ]; then
        echo "✓ Set permissions for yii.bat (444, non-writable, non-executable)"
    else
        echo "⚠ yii.bat permissions: $PERMS (target: 444), writable: $IS_WRITABLE, executable: $IS_EXECUTABLE"
        echo "  Note: With volume mounts, host filesystem permissions may override container permissions"
    fi
fi
# yii should be non-executable but readable (644)
if [ -f "/var/www/html/yii" ]; then
    # Try to set ownership first (we're root, should work)
    chown www-data:www-data /var/www/html/yii 2>/dev/null || true
    
    # Set permissions using multiple methods
    # Method 1: Direct chmod to 644
    chmod 644 /var/www/html/yii 2>/dev/null || true
    
    # Method 2: Remove execute bit explicitly
    chmod a-x /var/www/html/yii 2>/dev/null || true
    
    # Method 3: Use numeric mode again
    chmod 644 /var/www/html/yii 2>/dev/null || true
    
    # Verify permissions were set
    PERMS=$(stat -c "%a" /var/www/html/yii 2>/dev/null || echo "unknown")
    IS_EXECUTABLE=$(test -x /var/www/html/yii && echo "yes" || echo "no")
    
    if [ "$PERMS" = "644" ] && [ "$IS_EXECUTABLE" = "no" ]; then
        echo "✓ Set permissions for yii (644, non-executable)"
    else
        echo "⚠ yii permissions: $PERMS (target: 644), executable: $IS_EXECUTABLE"
        echo "  Note: With volume mounts, host filesystem permissions may override container permissions"
    fi
fi

# Try to change ownership if possible (may fail with volume mounts, but try anyway)
chown -R www-data:www-data /var/www/html/runtime 2>/dev/null || true
chown -R www-data:www-data /var/www/html/web/assets 2>/dev/null || true
chown -R www-data:www-data /var/www/html/logger 2>/dev/null || true
chown -R www-data:www-data /var/www/html/modules/cds/content 2>/dev/null || true

# Ensure install.lock can be created (if directory is writable)
if [ -w "/var/www/html" ]; then
    echo "=== /var/www/html is writable ==="
else
    echo "=== WARNING: /var/www/html is not writable, install.lock creation may fail ==="
    # Try to make it writable
    chmod 775 /var/www/html 2>/dev/null || true
fi

# Configure opcache based on environment variable (default: enabled for production)
ENABLE_OPCACHE=${ENABLE_OPCACHE:-true}
if [ "$ENABLE_OPCACHE" = "false" ]; then
    echo "=== Disabling opcache for development ==="
    echo "opcache.enable=0" > /usr/local/etc/php/conf.d/opcache.ini || true
else
    echo "=== Opcache is enabled for production (optimal performance) ==="
    # Opcache is already configured in Dockerfile
fi

# Update PHP-FPM pool configuration if not already set
# This ensures settings are applied even without rebuilding the image
if [ -f "/usr/local/etc/php-fpm.d/www.conf" ]; then
    echo "=== Updating PHP-FPM pool configuration ==="
    # Update pm.max_children if it's less than 40
    if grep -q "^pm.max_children =[[:space:]]*[0-3][0-9]$" /usr/local/etc/php-fpm.d/www.conf 2>/dev/null; then
        CURRENT_MAX=$(grep "^pm.max_children" /usr/local/etc/php-fpm.d/www.conf | head -1 | sed 's/.*= *\([0-9]*\).*/\1/')
        if [ "$CURRENT_MAX" -lt 40 ] 2>/dev/null; then
            sed -i 's/^pm.max_children =.*/pm.max_children = 40/' /usr/local/etc/php-fpm.d/www.conf
            echo "Updated pm.max_children to 40"
        fi
    fi
    # Update other settings to higher values for better performance
    sed -i 's/^pm.start_servers =.*/pm.start_servers = 10/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
    sed -i 's/^pm.min_spare_servers =.*/pm.min_spare_servers = 5/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
    sed -i 's/^pm.max_spare_servers =.*/pm.max_spare_servers = 20/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
    sed -i 's/^request_terminate_timeout =.*/request_terminate_timeout = 120s/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true
fi

# Always ensure we can start PHP-FPM
echo "=== Starting PHP-FPM ==="
echo "Command to execute: $@"

# Execute the original command (should be php-fpm)
exec "$@"

