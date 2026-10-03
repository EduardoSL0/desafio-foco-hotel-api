#!/bin/sh
set -e

cd /var/www/html

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    # Sinal de "pronto" de uma execução anterior não vale: os auxiliares esperam este boot terminar.
    rm -f storage/.ready

    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    # Instala/atualiza dependências quando o volume vendor está vazio ou o composer.lock mudou.
    if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/composer/installed.json ]; then
        composer install --no-interaction --prefer-dist
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        php artisan key:generate --force
    fi

    # Ao religar o Docker os containers sobem juntos (o depends_on só vale no "up"): espera o MySQL aceitar conexões.
    if [ "${DB_CONNECTION:-}" = "mysql" ]; then
        tries=0
        until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }'; do
            tries=$((tries + 1))
            [ "$tries" -ge 40 ] && break
            echo "Aguardando o MySQL..."
            sleep 3
        done
    fi

    php artisan migrate --force

    if [ "${SEED_ON_START:-true}" = "true" ]; then
        php artisan db:seed --force
    fi

    chmod -R 777 storage bootstrap/cache || true
    touch storage/.ready
else
    # Containers auxiliares (scheduler) aguardam o container "app" preparar o projeto.
    until [ -f storage/.ready ] && [ -f vendor/autoload.php ]; do
        echo "Aguardando o container app ficar pronto..."
        sleep 3
    done
fi

exec "$@"
