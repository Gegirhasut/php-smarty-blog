# Демо «всё в одном» для Render: nginx, php-fpm и MySQL 8.4 в одном контейнере (бесплатный план - один сервис, 512 МБ).
# Сборка из корня репозитория: docker build -f docker/demo/Dockerfile -t blog-demo .
# Render не умеет выбирать стадию сборки, поэтому это отдельный Dockerfile, а не стадия основного.

# Стили: тот же dart-sass, что в основном Dockerfile; версию и суммы меняйте в обоих
FROM alpine:3.22 AS sass
ARG SASS_VERSION=1.107.1
ARG SASS_SHA256_X64=80c2c205f22cefd223775830862c93717bbb7e9b275a6699f44e3267f96668e2
ARG SASS_SHA256_ARM64=a0a2fb36c68522c967361da656847e238172f8e2c7688a7d04a3349ddb48eff7
ARG TARGETARCH
RUN case "$TARGETARCH" in arm64) arch=arm64 sum=$SASS_SHA256_ARM64 ;; *) arch=x64 sum=$SASS_SHA256_X64 ;; esac \
    && wget -qO /tmp/sass.tar.gz "https://github.com/sass/dart-sass/releases/download/${SASS_VERSION}/dart-sass-${SASS_VERSION}-linux-${arch}-musl.tar.gz" \
    && echo "$sum  /tmp/sass.tar.gz" | sha256sum -c - \
    && tar -xzf /tmp/sass.tar.gz -C /opt \
    && rm /tmp/sass.tar.gz
WORKDIR /app
COPY assets assets
RUN /opt/dart-sass/sass --no-source-map --style=compressed assets/scss/app.scss app.css

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
COPY src src
RUN composer install --no-dev --no-interaction --no-progress --classmap-authoritative

FROM php:8.5-fpm-trixie

# MySQL 8.4 LTS из репозитория Oracle: в Debian его нет. Ключ продлён до 2027-10-23, потом его нужно обновить
ARG MYSQL_GPG_SHA256=a4bcd9f16a53cc763f87b9955dbcdced33c7aa90296b157eb6ceef0f156f4327
RUN install -d /etc/apt/keyrings \
    && curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 -o /etc/apt/keyrings/mysql.asc \
    && echo "$MYSQL_GPG_SHA256  /etc/apt/keyrings/mysql.asc" | sha256sum -c - \
    && echo 'deb [signed-by=/etc/apt/keyrings/mysql.asc] https://repo.mysql.com/apt/debian trixie mysql-8.4-lts' \
        > /etc/apt/sources.list.d/mysql.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends mysql-community-server-core nginx tini \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && groupadd --system mysql && useradd --system --gid mysql --home-dir /var/lib/mysql mysql \
    && install -d -o mysql -g mysql /var/lib/mysql /run/mysqld

COPY docker/demo/my.cnf /etc/mysql/my.cnf
COPY docker/demo/nginx.conf /etc/nginx/nginx.conf
COPY docker/demo/php.ini "$PHP_INI_DIR/conf.d/zz-demo.ini"
COPY docker/demo/php-fpm.conf /usr/local/etc/php-fpm.d/zz-demo.conf
COPY docker/demo/start.sh /usr/local/bin/start-demo
# Правила сайта - из конфига разработки, start-demo подставит в него порт и адрес php-fpm
COPY docker/nginx/default.conf /etc/nginx/site.conf.dist

# Системные таблицы MySQL создаются при сборке: на 0,1 CPU бесплатного плана это заняло бы при каждом старте
# лишние десятки секунд
RUN mysqld --initialize-insecure \
    && chmod +x /usr/local/bin/start-demo \
    && grep -q 'listen 80;' /etc/nginx/site.conf.dist \
    && grep -q 'fastcgi_pass php:9000;' /etc/nginx/site.conf.dist

WORKDIR /var/www/html
COPY --from=vendor /app/vendor vendor
COPY bin bin
COPY database database
COPY public public
COPY src src
COPY templates templates
COPY --from=sass /app/app.css public/assets/css/app.css
RUN install -d -o www-data -g www-data var

ENV APP_ENV=prod \
    DB_HOST=localhost \
    DB_NAME=blog \
    DB_USER=blog

# База засевается при сборке: в песочнице Render на 0,1 CPU сидер при каждом старте занимал больше минуты.
# При старте MySQL открывает готовые данные, а пароль пользователя сайта задаётся заново
RUN start-demo build

# Образ php останавливают SIGQUIT (мягкая остановка php-fpm), а start-demo по SIGTERM останавливает все три процесса
STOPSIGNAL SIGTERM
ENTRYPOINT ["tini", "--", "start-demo"]
