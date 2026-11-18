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
    // Сначала таблица связей: её внешние ключи ссылаются на остальные
    private const TABLES = ['category_post', 'posts', 'categories'];

    protected PDO $pdo;
    protected Schema $schema;

    public static function setUpBeforeClass(): void
    {
        // DROP и CREATE в MySQL занимают около полусекунды, поэтому схема пересоздаётся раз на класс
        // (так подхватываются правки schema.sql), а перед каждым тестом таблицы только очищаются
        $schema = self::schema(self::connect());
        $schema->drop();
        $schema->create();
    }

    protected function setUp(): void
    {
        $this->pdo = self::connect();
        $this->schema = self::schema($this->pdo);

        // Предыдущий тест мог удалить таблицы, а на существующих create() ничего не меняет
        $this->schema->create();
        foreach (self::TABLES as $table) {
            $this->pdo->exec("DELETE FROM {$table}");
        }
        // Тесты сверяют id, поэтому нумерация каждый раз начинается с единицы
        $this->pdo->exec('ALTER TABLE posts AUTO_INCREMENT = 1');
        $this->pdo->exec('ALTER TABLE categories AUTO_INCREMENT = 1');
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

    private static function connect(): PDO
    {
        return Connection::fromEnvironment(Env::get('DB_TEST_NAME', 'blog_test'))->connect();
    }

    private static function schema(PDO $pdo): Schema
    {
        return new Schema($pdo, dirname(__DIR__, 2) . '/database/schema.sql');
    }
}
