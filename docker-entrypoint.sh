#!/usr/bin/env sh
set -e

if [ "${DB_CONNECTION:-}" = "mysql" ]; then
  echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
  until php -r '
    $host = getenv("DB_HOST");
    $port = getenv("DB_PORT") ?: "3306";
    $db = getenv("DB_DATABASE");
    $user = getenv("DB_USERNAME");
    $pass = getenv("DB_PASSWORD") ?: "";
    try {
        new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass);
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
  '; do
    sleep 2
  done
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

php artisan config:clear --no-interaction || true
php artisan cache:clear --no-interaction || true
php artisan migrate --force --no-interaction

exec "$@"
