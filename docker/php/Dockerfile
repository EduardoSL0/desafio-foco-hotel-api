FROM php:8.3-fpm-alpine

# Certificados raiz extras opcionais (ex.: antivírus/proxy corporativo que inspeciona HTTPS).
# Coloque arquivos .crt (PEM) em docker/certs/ — a pasta é ignorada pelo Git.
COPY docker/certs/ /tmp/extra-certs/
RUN for c in /tmp/extra-certs/*.crt; do [ -f "$c" ] && cat "$c" >> /etc/ssl/certs/ca-certificates.crt; done; rm -rf /tmp/extra-certs

RUN apk add --no-cache libzip-dev zip unzip git \
    && docker-php-ext-install pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
