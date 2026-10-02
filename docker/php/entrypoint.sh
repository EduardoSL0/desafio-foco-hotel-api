#!/bin/sh
set -e

cd /var/www/html

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    if [ ! -f vendor/autoload.php ]; then
        composer install --no-interaction --prefer-dist
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        php artisan key:generate --force
    fi

    php artisan migrate --force

    if [ "${SEED_ON_START:-true}" = "true" ]; then
        php artisan db:seed --force
    fi

    chmod -R 777 storage bootstrap/cache || true
    touch storage/.ready
else
    # Containers auxiliares (scheduler) aguardam o container "app" preparar o projeto.
    until [ -f storage/.ready ]; do
        echo "Aguardando o container app ficar pronto..."
        sleep 3
    done
fi

exec "$@"
