<?php

declare(strict_types=1);

namespace App\Tests\Integration\Database;

use App\Tests\DatabaseTestCase;
use PDO;
use PDOException;

final class SchemaTest extends DatabaseTestCase
{
    public function testCreatesTables(): void
    {
        $tables = $this->select('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        self::assertSame(['categories', 'category_post', 'posts'], $tables);
    }

    public function testCreateCanBeRepeated(): void
    {
        $this->insertPostInCategory();

        $this->schema->create();

        $this->assertRowCount(1, 'posts');
    }

    public function testDropRemovesTables(): void
    {
        $this->schema->drop();

        self::assertSame([], $this->select('SHOW TABLES')->fetchAll());
    }

    public function testSlugsAreUnique(): void
    {
        $this->insertPostInCategory();

        $this->expectException(PDOException::class);
        $this->pdo->exec("INSERT INTO categories (name, slug, description) VALUES ('Дубль', 'travel', '')");
    }

    public function testSlugsMatchOnlyExactly(): void
    {
        $this->insertPostInCategory();

        self::assertSame(0, $this->select("SELECT COUNT(*) FROM categories WHERE slug = 'Travel'")->fetchColumn());
        self::assertSame(0, $this->select("SELECT COUNT(*) FROM posts WHERE slug = 'BAIKAL'")->fetchColumn());
    }

    public function testSlugsAreAsciiOnly(): void
    {
        $this->expectException(PDOException::class);

        $this->pdo->exec("INSERT INTO categories (name, slug, description) VALUES ('Еда', 'еда', '')");
    }

    public function testDeletingPostRemovesItsCategoryLinks(): void
    {
        $this->insertPostInCategory();

        $this->pdo->exec('DELETE FROM posts');

        $this->assertRowCount(0, 'category_post');
        $this->assertRowCount(1, 'categories');
    }

    private function insertPostInCategory(): void
    {
        $this->pdo->exec("INSERT INTO categories (id, name, slug, description) VALUES (1, 'Путешествия', 'travel', '')");
        $this->pdo->exec(
            "INSERT INTO posts (id, title, slug, image, description, content, published_at)
             VALUES (1, 'Байкал', 'baikal', '/uploads/baikal.webp', '', '', '2026-10-01 10:00:00')",
        );
        $this->pdo->exec('INSERT INTO category_post (category_id, post_id) VALUES (1, 1)');
    }
}
