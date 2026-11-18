<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

set_error_handler(function (int $level, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $level)) {
        return false;
    }

    throw new ErrorException($message, 0, $level, $file, $line);
});

// Если окружение не настроено, исключение из Config::load() PHP запишет в лог, а в проде ответит 500
$app = new App(Config::load(), dirname(__DIR__));
$app->handle(Request::fromGlobals())->send();
