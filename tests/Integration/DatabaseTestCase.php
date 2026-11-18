<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Core\Env;
use App\Database\Connection;
use App\Database\Schema;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;
    protected Schema $schema;

    protected function setUp(): void
    {
        $this->pdo = Connection::fromEnvironment(Env::get('DB_TEST_NAME', 'blog_test'))->connect();
        $this->schema = new Schema($this->pdo, dirname(__DIR__, 2) . '/database/schema.sql');
        $this->schema->drop();
        $this->schema->create();
    }

    protected function select(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute();

        return $statement;
    }

    protected function assertRowCount(int $expected, string $table): void
    {
        self::assertSame($expected, $this->select("SELECT COUNT(*) FROM {$table}")->fetchColumn());
    }
}
