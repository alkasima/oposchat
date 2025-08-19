FROM php:8.2

# Install system dependencies and Node.js 20.x
RUN apt-get update && apt-get install -y \
    curl git unzip zip libpng-dev libonig-dev libxml2-dev libzip-dev gnupg ca-certificates \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy Laravel app files
COPY . .

# Copy CA certificate into container
COPY storage/certs/ca.pem /etc/ssl/certs/ca.pem

COPY database/database.sqlite /var/www/database/database.sqlite

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install JS dependencies and build frontend assets using Vite
RUN npm install && npm run build

# Set correct permissions
RUN chmod -R 755 storage bootstrap/cache

# Create entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port for Laravel app
EXPOSE 8080

# Use entrypoint script
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
