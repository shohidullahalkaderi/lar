FROM php:8.4-cli

# Install only the absolute essentials for Composer and MySQL/Redis drivers
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        zip \
        unzip \
    && docker-php-ext-install pdo_mysql bcmath zip \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /usr/src/app

# Copy composer files first (for better layer caching)
COPY composer.json composer.lock* ./

# Expose HTTP port (artisan serve)
EXPOSE 8000

# Default command – runs the Laravel development server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]