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

# NOTE: kubelet does NOT read this HEALTHCHECK instruction — your
# Deployment's startupProbe/readinessProbe/livenessProbe (tcpSocket on
# 8000) are what actually govern pod health in the cluster. Kept only
# for standalone `docker run` debugging; harmless, not a production risk.
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD nc -z 0 8000 || exit 1

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]