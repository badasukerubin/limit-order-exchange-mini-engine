#!/bin/bash

echo "Setting up Limit-Order Exchange..."

cp .env.example .env

composer install --no-interaction --prefer-dist

php artisan key:generate

php artisan migrate --seed

npm install

php artisan solo

echo "Setup complete!"
echo "Login using seeded test users or create a new account."
