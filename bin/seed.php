#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Schema;
use App\Database\Seeder;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

// PDOException тоже RuntimeException: недоступная база, ошибка схемы и плохие данные выводятся одной строкой
try {
    $pdo = Connection::fromEnvironment()->connect();
    $schema = new Schema($pdo, $root . '/database/schema.sql');

    if (in_array('--fresh', $argv ?? [], true)) {
        $schema->drop();
    }
    $schema->create();

    $seeder = new Seeder($pdo, new CategoryRepository($pdo), new PostRepository($pdo), $root . '/database/seeds');
    $result = $seeder->run();
} catch (RuntimeException $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Добавлено категорий: {$result['categories']}, статей: {$result['posts']}\n";
