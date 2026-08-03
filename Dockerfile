# Stage : Builder
FROM php:8.4-cli AS builder

WORKDIR /usr/src/app

# Install build dependencies & PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        zip \
        unzip \
    && docker-php-ext-install pdo_mysql bcmath zip \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Install Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy dependency manifests
COPY composer.json* composer.lock* ./

# Install vendor dependencies (optimized for production build)
RUN if [ -f composer.json ]; then \
        composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist --optimize-autoloader; \
    else \
        echo "composer.json not found, skipping installation."; \
    fi

# Stage : Production Runtime
FROM php:8.4-cli AS runner

WORKDIR /usr/src/app

# Install runtime dependencies including netcat-openbsd for nc healthcheck
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        netcat-openbsd \
    && docker-php-ext-install pdo_mysql bcmath zip \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Copy installed composer packages from builder stage
COPY --from=builder /usr/src/app/vendor ./vendor

# Copy codebase with correct ownership
COPY --chown=www-data:www-data . .

# Set permissions for Laravel storage & cache directories
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Switch to non-root execution (www-data UID/GID 33)
USER www-data

EXPOSE 8000

# Universal container healthcheck using netcat
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD nc -z 0 8000 || exit 1

# Production server entrypoint
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]