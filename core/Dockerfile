FROM php:8.1-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libsnmp-dev \
    libgmp-dev \
    libicu-dev \
    snmp \
    libssh2-1-dev \
    libzip-dev \
    zip \
    unzip \
    default-mysql-client \
    && docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gmp zip snmp intl \
    && pecl install ssh2-1.3.1 \
    && docker-php-ext-enable ssh2 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
# Using composer:2.8 for stability (latest is 2.8.12 as of Nov 2025)
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy all application files first
COPY . /var/www/html

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Install PHP dependencies (will create vendor directory)
# This will be in the image, but may be overridden by volume mount
# Use --no-plugins to avoid plugin compatibility issues with Composer 2.8+
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-plugins --ignore-platform-reqs || true

# Create necessary directories
RUN mkdir -p /var/www/html/runtime \
    && mkdir -p /var/www/html/runtime/sessions \
    && mkdir -p /var/www/html/web/assets \
    && mkdir -p /var/www/html/logger

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/runtime \
    && chmod -R 775 /var/www/html/web/assets \
    && if [ -f /var/www/html/bin/cbackup.jar ]; then chmod 555 /var/www/html/bin/cbackup.jar; chmod +x /var/www/html/bin/cbackup.jar; fi \
    && if [ -f /var/www/html/yii.bat ]; then chmod 444 /var/www/html/yii.bat; fi \
    && if [ -f /var/www/html/yii ]; then chmod 644 /var/www/html/yii; chmod -x /var/www/html/yii; fi

# Configure PHP to suppress deprecated warnings for ReflectionParameter::getClass()
# This is used by older Yii2 versions and doesn't affect functionality in PHP 8.0+
RUN echo "error_reporting = E_ALL & ~E_DEPRECATED" > /usr/local/etc/php/conf.d/deprecated.ini

# Configure opcache - enable by default for production
# Set ENABLE_OPCACHE=false in docker-compose.yml to disable for development
RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.revalidate_freq=2" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini || true

# Update PHP-FPM pool configuration to increase process limit
# Modify the default www.conf file directly to ensure settings are applied
RUN sed -i 's/^pm.max_children =.*/pm.max_children = 40/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^pm.start_servers =.*/pm.start_servers = 10/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^pm.min_spare_servers =.*/pm.min_spare_servers = 5/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^pm.max_spare_servers =.*/pm.max_spare_servers = 20/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^request_terminate_timeout =.*/request_terminate_timeout = 120s/' /usr/local/etc/php-fpm.d/www.conf || \
    (echo "" >> /usr/local/etc/php-fpm.d/www.conf && \
     echo "; Custom PHP-FPM pool settings" >> /usr/local/etc/php-fpm.d/www.conf && \
     echo "pm.max_children = 40" >> /usr/local/etc/php-fpm.d/www.conf && \
     echo "pm.start_servers = 10" >> /usr/local/etc/php-fpm.d/www.conf && \
     echo "pm.min_spare_servers = 5" >> /usr/local/etc/php-fpm.d/www.conf && \
     echo "pm.max_spare_servers = 20" >> /usr/local/etc/php-fpm.d/www.conf && \
     echo "request_terminate_timeout = 120s" >> /usr/local/etc/php-fpm.d/www.conf)

# Set Docker environment variable for application detection
ENV DOCKER_CONTAINER=true
ENV container=docker

# Expose port
EXPOSE 9000

# Use entrypoint to ensure dependencies are installed
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

CMD ["php-fpm"]

