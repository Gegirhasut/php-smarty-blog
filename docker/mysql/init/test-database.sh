# Отдельная база для интеграционных тестов, чтобы они не трогали данные сайта.
# Образ MySQL выполняет скрипт при первом создании тома.
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS blog_test;
GRANT ALL ON blog_test.* TO '${MYSQL_USER}'@'%';
SQL
