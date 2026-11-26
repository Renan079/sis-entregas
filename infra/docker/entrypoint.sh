#!/bin/sh
set -e

# Garante permissões de escrita para o PHP-FPM (www-data)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Aguarda o PostgreSQL aceitar conexões antes de rodar migrations
echo "Aguardando banco de dados PostgreSQL..."
until php -r "try { new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Exception \$e) { exit(1); }"; do
  sleep 2
done

echo "Executando migrations e caches de produção..."
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"