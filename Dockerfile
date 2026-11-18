# Сборщик стилей: dart-sass одним бинарником, без Node.js. В compose это сервис sass,
# образ для демо возьмёт отсюда же сборку CSS
FROM alpine:3.22 AS sass
# Суммы архивов - из релиза на GitHub; при смене версии их нужно обновить
ARG SASS_VERSION=1.107.1
ARG SASS_SHA256_X64=80c2c205f22cefd223775830862c93717bbb7e9b275a6699f44e3267f96668e2
ARG SASS_SHA256_ARM64=a0a2fb36c68522c967361da656847e238172f8e2c7688a7d04a3349ddb48eff7
ARG TARGETARCH
RUN case "$TARGETARCH" in arm64) arch=arm64 sum=$SASS_SHA256_ARM64 ;; *) arch=x64 sum=$SASS_SHA256_X64 ;; esac \
    && wget -qO /tmp/sass.tar.gz "https://github.com/sass/dart-sass/releases/download/${SASS_VERSION}/dart-sass-${SASS_VERSION}-linux-${arch}-musl.tar.gz" \
    && echo "$sum  /tmp/sass.tar.gz" | sha256sum -c - \
    && tar -xzf /tmp/sass.tar.gz -C /opt \
    && rm /tmp/sass.tar.gz
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
