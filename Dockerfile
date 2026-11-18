# Сборщик стилей: dart-sass одним бинарником, без Node.js. В compose это сервис sass,
# образ для демо возьмёт отсюда же сборку CSS
FROM alpine:3.22 AS sass
ARG SASS_VERSION=1.107.1
ARG TARGETARCH
RUN case "$TARGETARCH" in arm64) arch=arm64 ;; *) arch=x64 ;; esac \
    && wget -qO- "https://github.com/sass/dart-sass/releases/download/${SASS_VERSION}/dart-sass-${SASS_VERSION}-linux-${arch}-musl.tar.gz" \
    | tar -xz -C /opt
ENV PATH="/opt/dart-sass:$PATH"
WORKDIR /app

# PHP для разработки: compose собирает эту стадию, она последняя
FROM php:8.5-fpm-alpine
RUN docker-php-ext-install pdo_mysql \
    && mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/expose.ini"
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html
RUN mkdir var && chown www-data:www-data var
