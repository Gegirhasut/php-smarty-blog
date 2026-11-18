#!/bin/bash
# Запуск демо: MySQL с базой, засеянной при сборке образа, затем php-fpm и nginx. Изменения (просмотры) живут
# до перезапуска контейнера. Если любой из процессов завершится, контейнер остановится целиком,
# а по SIGTERM останавливаются все три.
# start-demo build - вызов из Dockerfile: поднять MySQL, применить схему и засеять базу, остановить MySQL
set -euo pipefail
mode="${1:-run}"

# Render передаёт порт в PORT, а адрес сервиса - в RENDER_EXTERNAL_URL
export PORT="${PORT:-10000}"
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"
# Пароль нужен только внутри контейнера, поэтому свой на каждый запуск, а не в образе
DB_PASSWORD="$(head -c 18 /dev/urandom | base64 | tr -d '/+')"
export DB_PASSWORD
# В песочнице Render на 0,1 CPU MySQL может стартовать несколько минут
MYSQL_WAIT=600

log() {
    echo "[demo] $*" >&2
}

pids=()
stop() {
    trap - TERM INT
    kill -TERM "${pids[@]}" 2>/dev/null || true
    wait || true
}
trap 'stop; exit 0' TERM INT

# База и пользователь сайта создаются при сборке, при каждом старте пользователь получает новый пароль.
# Сайт ходит в MySQL через сокет (DB_HOST=localhost), поэтому пользователь - 'blog'@'localhost'
init=/run/mysqld/init.sql
cat > "$init" <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME};
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
SQL
chown mysql "$init"
# Журнал MySQL идёт в лог контейнера и копией в файл: если сервер не поднимется, причина будет в конце лога
mysql_log=/run/mysqld/error.log
log "запуск MySQL, CPU $(nproc)"
mysqld --init-file="$init" > >(tee "$mysql_log" >&2) 2>&1 &
pids+=("$!")

# Готовность проверяет то же подключение, что у сайта и сидера: Connection из переменных окружения.
# Пока нет сокета, PHP не запускается: на 0,1 CPU каждая попытка отнимала бы время у самого MySQL
started=$SECONDS
tries=0
error='сокет MySQL ещё не создан'
until [[ -S /run/mysqld/mysqld.sock ]] && error="$(php -r 'require "vendor/autoload.php";
        try { App\Database\Connection::fromEnvironment()->connect(); } catch (Throwable $e) { echo $e->getMessage(); exit(1); }')"; do
    if ! kill -0 "${pids[0]}" 2>/dev/null; then
        # Код больше 128 - процесс убит сигналом: 137 - SIGKILL (обычно память), 159 - SIGSYS (запрещённый вызов)
        code=0
        wait "${pids[0]}" || code=$?
        log "MySQL не запустился: код ${code}. Конец журнала MySQL:"
        tail -n 30 "$mysql_log" >&2
        exit 1
    fi
    if (( SECONDS - started >= MYSQL_WAIT )); then
        log "MySQL не готов за ${MYSQL_WAIT} с. Последняя ошибка подключения: ${error}"
        stop
        exit 1
    fi
    if (( ++tries % 15 == 0 )); then
        log "ждём MySQL $(( SECONDS - started )) с: ${error}"
    fi
    sleep 2
done
rm "$init"
log "MySQL готов за $(( SECONDS - started )) с"

if [[ $mode == build ]]; then
    php bin/seed.php --fresh
    stop
    rm "$mysql_log"
    exit 0
fi

sed -e "s/listen 80;/listen ${PORT};/" -e 's/fastcgi_pass php:9000;/fastcgi_pass 127.0.0.1:9000;/' \
    /etc/nginx/site.conf.dist > /etc/nginx/site.conf
log "запуск php-fpm"
php-fpm &
pids+=("$!")
log "запуск nginx на порту ${PORT}, адрес сайта ${APP_URL}"
nginx -g 'daemon off;' &
pids+=("$!")

# Первый завершившийся процесс останавливает остальные
status=0
wait -n || status=$?
log "процесс завершился с кодом ${status}, контейнер останавливается"
stop
exit $(( status == 0 ? 1 : status ))
