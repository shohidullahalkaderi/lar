docker builder prune -f
docker compose down --remove-orphans
docker compose down -v
docker compose build --no-cache
docker compose up -d
docker compose exec app php artisan migrate
docker exec laravel_redis redis-cli -a password_secure PING
docker exec laravel_mysql_db mysqladmin -u laravel_user -plaravel_password ping