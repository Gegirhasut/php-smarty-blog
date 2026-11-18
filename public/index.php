<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Request;
use App\Database\Connection;

require dirname(__DIR__) . '/vendor/autoload.php';

set_error_handler(function (int $level, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $level)) {
        return false;
    }

    throw new ErrorException($message, 0, $level, $file, $line);
});

// Время в базе (published_at) - UTC; в нём же даты выводятся на страницах, в RSS, sitemap и JSON-LD
date_default_timezone_set('UTC');

// Если окружение не настроено, исключение из Config::load() PHP запишет в лог, а в проде ответит 500.
// Соединение с БД App откроет сам, когда оно понадобится странице
$app = new App(Config::load(), dirname(__DIR__), fn (): PDO => Connection::fromEnvironment()->connect());
$app->handle(Request::fromGlobals())->send();
