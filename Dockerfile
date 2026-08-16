# local-image
FROM dunglas/frankenphp:1-php8.4

# Install system dependencies and netcat for healthchecks
RUN apt-get update && apt-get install -y --no-install-recommends \
    netcat-openbsd \
    libcap2-bin \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions safely using the built-in helper
RUN install-php-extensions \
    pdo_mysql \
    bcmath \
    zip \
    pcntl \
    redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /usr/src/app

# Copy composer files first
COPY composer.json* composer.lock* ./

RUN [ -f composer.json ] && composer install --no-interaction --no-plugins --no-scripts --prefer-dist || \
    echo "composer.json not found, skipping installation."

# Explicitly copy remaining codebase into WORKDIR
COPY . .

# Expose HTTP port 
EXPOSE 8000

# Original netcat HEALTHCHECK restored
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD nc -z 0 8000 || exit 1

# Runs Laravel Octane with FrankenPHP
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]