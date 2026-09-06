FROM serversideup/php:8.4-fpm-nginx

ENV PHP_EXT_ENABLE="pdo_pgsql,pgsql,zip"
ENV AUTORUN_LARAVEL_MIGRATION="true"
ENV AUTORUN_LARAVEL_CONFIG_CACHE="true"

WORKDIR /var/www/html

COPY backend/ .

USER root
RUN mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache \
    && chown -R 9999:9999 /var/www/html

USER 9999
RUN composer install --no-dev --optimize-autoloader --no-interaction

USER root
