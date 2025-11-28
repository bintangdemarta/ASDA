#!/bin/bash

# Laravel Docker Setup Script
# This script prepares the Laravel application for Docker environment

set -e  # Exit on any error

echo "Starting Laravel Docker setup..."

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

# Test the connection one final time
if ! php -r "new PDO('mysql:host=mysql;dbname=admin_panel', 'admin_panel', 'admin_panel');" >/dev/null 2>&1; then
    echo "ERROR: MySQL is not accessible after waiting! Check database configuration."
    exit 1
fi

cd /var/www/html

# Only attempt to fix permissions if running as root initially
# The www-data user can't change permissions on Windows-mounted volumes
if [ "$(id -u)" = "0" ]; then
    echo "Setting up permissions (as root)..."
    # Create directories if they don't exist and set permissions
    mkdir -p storage/logs
    mkdir -p bootstrap/cache
    # Since Windows volume mounts make these read-only for non-root users, we'll just ensure the directories exist
fi

# Install PHP dependencies if not already installed
if [ ! -d "vendor" ] || [ -z "$(ls -A vendor 2>/dev/null)" ]; then
    echo "Installing PHP dependencies..."
    composer install --no-interaction --optimize-autoloader --no-dev
else
    echo "PHP dependencies already installed."
fi

# Generate application key if not already set
if [ -z "$(grep 'APP_KEY=' .env | grep -v '^#' | head -n1)" ] || [ "$(grep 'APP_KEY=' .env | grep -v '^#' | cut -d'=' -f2)" = "" ] || [ "$(grep 'APP_KEY=' .env | grep -v '^#' | cut -d'=' -f2)" = "base64:" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
else
    echo "Application key already exists."
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Seed the database if needed
if [ -f "database/seeders/DatabaseSeeder.php" ]; then
    echo "Seeding database..."
    php artisan db:seed --force
fi

# Cache configuration for better performance
if [ "${APP_ENV:-local}" = "production" ]; then
    echo "Caching configuration for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "Clearing caches for development..."
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear
fi

echo "Laravel application setup completed successfully!"

# Execute the original command
exec "$@"