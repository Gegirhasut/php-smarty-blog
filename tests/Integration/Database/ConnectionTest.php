<?php

declare(strict_types=1);

namespace App\Tests\Integration\Database;

use App\Tests\DatabaseTestCase;
use PDO;
use PDOException;

final class ConnectionTest extends DatabaseTestCase
{
    public function testUsesUtf8mb4(): void
    {
        self::assertSame('utf8mb4', $this->select('SELECT @@character_set_connection')->fetchColumn());
    }

    public function testReturnsNativeTypes(): void
    {
        $row = $this->select("SELECT 42 AS answer, 'текст' AS word")->fetch();

        self::assertSame(['answer' => 42, 'word' => 'текст'], $row);
    }

    public function testBindsLimitAndOffsetAsNumbers(): void
    {
        // С эмуляцией prepare PDO подставил бы '2' и '1' строками, и MySQL отверг бы такой LIMIT
        $statement = $this->pdo->prepare(
            'SELECT n FROM (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) t ORDER BY n LIMIT ? OFFSET ?',
        );
        $statement->execute([2, 1]);

        self::assertSame([2, 3], $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testThrowsOnSqlError(): void
    {
        $this->expectException(PDOException::class);

        $this->select('SELECT * FROM missing_table');
    }
}
