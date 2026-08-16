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

#-# #-# #-#

# production-image
# Stage : Builder
FROM dunglas/frankenphp:1-php8.4 AS builder

WORKDIR /usr/src/app

RUN install-php-extensions \
        pdo_mysql \
        zip \
        redis

# Install Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json* composer.lock* ./

RUN if [ -f composer.json ]; then \
        composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist --optimize-autoloader; \
    else \
        echo "composer.json not found, skipping installation."; \
    fi

COPY . .

RUN php artisan octane:install --server=frankenphp --no-interaction

# Pre-create all required Laravel storage subdirectories in builder
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache


# Stage : Production Runtime
FROM dunglas/frankenphp:1-php8.4 AS runner

WORKDIR /usr/src/app

# Install system dependencies and remove net capabilities in a single atomic layer
RUN apt-get update && apt-get install -y --no-install-recommends \
        netcat-openbsd \
        libcap2-bin \
    && setcap -r /usr/local/bin/frankenphp \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
        pdo_mysql \
        redis \
        pcntl

# Create non-root system user (UID/GID 10001)
RUN addgroup --gid 10001 appgroup \
    && adduser --uid 10001 --ingroup appgroup --disabled-password --no-create-home appuser

# Copy application code and directories with appuser ownership
COPY --from=builder --chown=10001:10001 /usr/src/app .

USER 10001

EXPOSE 8000

# Standalone docker healthcheck (Kubernetes relies on liveness/readiness probes instead)
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD nc -z 0 8000 || exit 1

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]