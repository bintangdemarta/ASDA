#!/bin/bash

# This script is used to run Laravel artisan commands inside the Docker container
# Usage: docker-compose exec php ./docker/scripts/artisan.sh [command]

cd /var/www/html

# Run the artisan command with all passed arguments
php artisan "$@"