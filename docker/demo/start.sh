#!/bin/bash
# Запуск демо: MySQL, затем схема и сидер, затем php-fpm и nginx. База каждый раз создаётся заново,
# данные живут до перезапуска контейнера. Если любой из процессов завершится, контейнер остановится целиком,
# а по SIGTERM останавливаются все три
set -euo pipefail

# Render передаёт порт в PORT, а адрес сервиса - в RENDER_EXTERNAL_URL
export PORT="${PORT:-10000}"
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"
# Пароль нужен только внутри контейнера, поэтому свой на каждый запуск, а не в образе
DB_PASSWORD="$(head -c 18 /dev/urandom | base64 | tr -d '/+')"
export DB_PASSWORD

pids=()
stop() {
    trap - TERM INT
    kill -TERM "${pids[@]}" 2>/dev/null || true
    wait || true
}
trap 'stop; exit 0' TERM INT

# Пользователь и база сайта создаются при старте MySQL; IF NOT EXISTS - на случай перезапуска того же контейнера
init=/run/mysqld/init.sql
cat > "$init" <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME};
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
SQL
chown mysql "$init"
# Журнал MySQL идёт в лог контейнера и копией в файл: если сервер не поднимется, причина будет в конце лога
mysql_log=/run/mysqld/error.log
mysqld --init-file="$init" > >(tee "$mysql_log" >&2) 2>&1 &
pids+=("$!")

until php -r 'try { new PDO("mysql:host=127.0.0.1;dbname=" . getenv("DB_NAME"), getenv("DB_USER"), getenv("DB_PASSWORD")); } catch (PDOException) { exit(1); }' 2>/dev/null; do
    if ! kill -0 "${pids[0]}" 2>/dev/null; then
        # Код больше 128 - процесс убит сигналом: 137 - SIGKILL (обычно память), 159 - SIGSYS (запрещённый вызов)
        code=0
        wait "${pids[0]}" || code=$?
        echo "MySQL не запустился: код ${code}, CPU $(nproc). Конец журнала MySQL:" >&2
        tail -n 30 "$mysql_log" >&2
        exit 1
    fi
    sleep 1
done
rm "$init"

php bin/seed.php --fresh

sed -e "s/listen 80;/listen ${PORT};/" -e 's/fastcgi_pass php:9000;/fastcgi_pass 127.0.0.1:9000;/' \
    /etc/nginx/site.conf.dist > /etc/nginx/site.conf
php-fpm &
pids+=("$!")
nginx -g 'daemon off;' &
pids+=("$!")
echo "Сайт готов: ${APP_URL}"

# Первый завершившийся процесс останавливает остальные
status=0
wait -n || status=$?
echo "Процесс завершился с кодом ${status}, контейнер останавливается" >&2
stop
exit $(( status == 0 ? 1 : status ))
