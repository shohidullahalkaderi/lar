# Clean up build cache and reset stack environment
docker builder prune -f
docker compose down --remove-orphans
docker compose down -v

# Rebuild microservice layers from scratch and boot
docker compose build --no-cache
docker compose up -d

# Execute framework migrations and verify directory state
docker compose exec app php artisan migrate
docker compose exec app ls -la /usr/src/app

# Verify engine health via Compose services using safe single-quoted credentials
docker compose exec redis redis-cli -a 'password_secure' PING
docker compose exec db mysqladmin -u laravel_user -p'laravel_password' ping