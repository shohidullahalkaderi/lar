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

# Seed the database with initial data
docker compose exec app php artisan db:seed
docker compose exec app env DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test