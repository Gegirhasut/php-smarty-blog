<?php

declare(strict_types=1);

namespace App\Tests;

use App\Core\Env;
use App\Database\Connection;
use App\Database\Schema;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Тесты на настоящем MySQL в отдельной базе blog_test.
 */
abstract class DatabaseTestCase extends TestCase
{
    // Сначала таблица связей: её внешние ключи ссылаются на остальные
    private const TABLES = ['category_post', 'posts', 'categories'];

    /**
     * Тест целиком в транзакции с откатом: вставки фикстур не ждут записи на диск.
     * Не подходит тестам, которые сами открывают транзакцию или меняют схему: DDL в MySQL завершает транзакцию.
     */
    protected bool $rollBack = false;

    // Схема уже пересоздана в этом запуске тестов
    private static bool $schemaCreated = false;

    protected PDO $pdo;
    protected Schema $schema;

    public static function setUpBeforeClass(): void
    {
        // DROP и CREATE в MySQL занимают около полусекунды, поэтому схема пересоздаётся раз за запуск
        // (так подхватываются правки schema.sql), а перед каждым тестом таблицы только очищаются
        if (!self::$schemaCreated) {
            $schema = self::schema(self::connect());
            $schema->drop();
            $schema->create();
            self::$schemaCreated = true;
        }
    }

    protected function setUp(): void
    {
        $this->pdo = self::connect();
        $this->schema = self::schema($this->pdo);
        self::clearTables($this->pdo);

        if ($this->rollBack) {
            $this->pdo->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        if ($this->rollBack) {
            $this->pdo->rollBack();
        }
    }

    protected static function connect(): PDO
    {
        return Connection::fromEnvironment(Env::get('DB_TEST_NAME', 'blog_test'))->connect();
    }

    /**
     * Одной транзакцией: MySQL сбрасывает на диск один коммит, а не три. Id после очистки продолжают расти,
     * поэтому тесты берут их из insertCategory() и insertPost(), а не рассчитывают на нумерацию с единицы.
     */
    protected static function clearTables(PDO $pdo): void
    {
        $pdo->beginTransaction();
        foreach (self::TABLES as $table) {
            $pdo->exec("DELETE FROM {$table}");
        }
        $pdo->commit();
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

    protected function insertCategory(string $slug, string $name = '', string $description = ''): int
    {
        $this->pdo->prepare('INSERT INTO categories (slug, name, description) VALUES (?, ?, ?)')
            ->execute([$slug, $name ?: "Категория {$slug}", $description ?: "Описание категории {$slug}."]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param list<int> $categoryIds
     */
    protected function insertPost(
        string $slug,
        array $categoryIds,
        string $publishedAt = '2026-10-01 10:00:00',
        int $views = 0,
        string $title = '',
        string $content = 'Текст статьи.',
        bool $cover = true,
    ): int {
        $this->pdo->prepare(
            'INSERT INTO posts (title, slug, image, description, content, views, published_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        )->execute([
            $title ?: "Статья {$slug}",
            $slug,
            $cover ? "/uploads/{$slug}.webp" : null,
            "Описание статьи {$slug}.",
            $content,
            $views,
            $publishedAt,
        ]);
        $postId = (int) $this->pdo->lastInsertId();

        // Первая категория в списке - основная; дата и просмотры в связке - копии полей статьи, как у сидера
        foreach ($categoryIds as $position => $categoryId) {
            $this->pdo->prepare(
                'INSERT INTO category_post (category_id, post_id, position, published_at, views) VALUES (?, ?, ?, ?, ?)',
            )->execute([$categoryId, $postId, $position, $publishedAt, $views]);
        }

        return $postId;
    }

    private static function schema(PDO $pdo): Schema
    {
        return new Schema($pdo, dirname(__DIR__) . '/database/schema.sql');
    }
}
