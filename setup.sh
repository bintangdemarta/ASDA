#!/bin/bash

cd /var/www/html

# Fix permissions
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage
chown -R www-data:www-data bootstrap/cache

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
for i in {1..60}; do
    if php -r "new PDO('mysql:host=mysql;dbname=admin_panel', 'admin_panel', 'admin_panel');" >/dev/null 2>&1; then
        echo "MySQL is ready!"
        break
    fi
    echo "Waiting for MySQL... ($i/60)"
    sleep 2
done

# Check if key is already generated
if [ ! -f .env ] || [ -z "$(grep 'APP_KEY=' .env | grep -v '^#' | cut -d'=' -f2 | grep -v 'base64:')" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Seed the database if needed
if [ -f "database/seeders/DatabaseSeeder.php" ]; then
    echo "Seeding database..."
    php artisan db:seed --force
fi

# Cache configuration for better performance in development
echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo "Setup completed successfully!"