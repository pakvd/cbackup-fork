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
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts || true

# Copy application files
COPY . /var/www/html

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

# Set Docker environment variable for application detection
ENV DOCKER_CONTAINER=true
ENV container=docker

# Expose port
EXPOSE 9000

CMD ["php-fpm"]

