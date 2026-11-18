<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

final class Schema
{
    // Порядок важен: сначала таблица связей, которая ссылается на остальные
    private const TABLES = ['category_post', 'posts', 'categories'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $file,
    ) {
    }

    public function create(): void
    {
        $sql = file_get_contents($this->file);

        if ($sql === false) {
            throw new RuntimeException("Не удалось прочитать схему {$this->file}");
        }

        // Делим по «;» без разбора SQL. Это верно, пока в schema.sql нет «;» внутри строк и комментариев,
        // процедур и триггеров: для схемы из одних CREATE TABLE этого достаточно
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                $this->pdo->exec($statement);
            }
        }
    }

    public function drop(): void
    {
        foreach (self::TABLES as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
