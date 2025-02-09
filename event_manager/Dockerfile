# Use the PHP base image with FPM
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    supervisor  # Install Supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath sockets

# Install Redis PHP extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . /var/www

# Install PHP dependencies
RUN composer install

# Add tymon/jwt-auth dependency
RUN composer require tymon/jwt-auth

# Publish JWT configuration
RUN php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"

# Generate JWT secret key
RUN php artisan jwt:secret

# Set proper permissions for storage and cache
RUN chmod -R 775 storage bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache

# Create necessary directories for Supervisor
RUN mkdir -p /var/log/supervisor && mkdir -p /var/run && chmod -R 777 /var/run && chmod -R 777 /var/log/supervisor

# Expose the application on port 9000
EXPOSE 9000

# Copy the Supervisor configuration file into the container
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Start Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
