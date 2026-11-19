#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Schema;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Seed\Seeder;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$usage = 'Запуск: php bin/seed.php [--fresh] [--posts=N]';

$fresh = false;
$generate = 0;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--fresh') {
        $fresh = true;
    } elseif (str_starts_with($arg, '--posts=')) {
        $generate = filter_var(substr($arg, strlen('--posts=')), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($generate === false) {
            fwrite(STDERR, "Ошибка: --posts ждёт целое число больше нуля\n{$usage}\n");
            exit(1);
        }
    } else {
        fwrite(STDERR, "Ошибка: неизвестный аргумент {$arg}\n{$usage}\n");
        exit(1);
    }
}

// PDOException тоже RuntimeException: недоступная база, ошибка схемы и плохие данные выводятся одной строкой
try {
    $pdo = Connection::fromEnvironment()->connect();
    $seeder = new Seeder(
        $pdo,
        new Schema($pdo, $root . '/database/schema.sql'),
        new CategoryRepository($pdo),
        new PostRepository($pdo),
        $root . '/database/seeds',
        $root . '/public/uploads',
    );
    $result = $seeder->run($generate, $fresh);
} catch (RuntimeException $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Добавлено категорий: {$result['categories']}, статей: {$result['posts']}\n";
