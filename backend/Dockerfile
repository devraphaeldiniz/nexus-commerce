FROM php:8.3-cli-alpine

RUN apk add --no-cache postgresql-dev bash curl $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
