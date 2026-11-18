<?php

declare(strict_types=1);

namespace App\Tests\Integration\Seed;

use App\Seed\Seeder;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Tests\DatabaseTestCase;
use App\Tests\TempDir;
use PDO;

/**
 * Настоящие данные из database/seeds: сидер должен залить их без ошибок.
 */
final class SeedDataTest extends DatabaseTestCase
{
    private static TempDir $uploads;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Залить настоящие данные - самое долгое в наборе, а тесты класса их только читают: заливаем один раз
        self::$uploads = new TempDir();
        $pdo = self::connect();
        self::clearTables($pdo);
        (new Seeder(
            $pdo,
            new CategoryRepository($pdo),
            new PostRepository($pdo),
            dirname(__DIR__, 3) . '/database/seeds',
            self::$uploads->path,
        ))->run();
    }

    public static function tearDownAfterClass(): void
    {
        self::$uploads->remove();
    }

    protected function setUp(): void
    {
        // Без очистки таблиц из DatabaseTestCase: данные залиты в setUpBeforeClass()
        $this->pdo = self::connect();
    }

    public function testFillsEveryCategory(): void
    {
        $this->assertRowCount(6, 'categories');
        $this->assertRowCount(49, 'posts');

        $postsPerCategory = $this->select(
            'SELECT c.slug, COUNT(cp.post_id) FROM categories c
             LEFT JOIN category_post cp ON cp.category_id = c.id
             GROUP BY c.slug',
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        self::assertCount(6, $postsPerCategory);
        self::assertNotContains(0, $postsPerCategory);
    }

    public function testCopiesEveryCover(): void
    {
        $images = $this->select('SELECT DISTINCT image FROM posts WHERE image IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);

        self::assertCount(24, $images);
        foreach ($images as $image) {
            self::assertIsString($image);
            self::assertFileExists(self::$uploads->path . '/' . basename($image));
        }
    }

    public function testOnePostWithoutCover(): void
    {
        // Одна статья без обложки показывает заглушку; несколько рядом смотрелись бы как поломка
        self::assertSame(
            ['alibaba-qwen4-max-million-tokenov'],
            $this->select('SELECT slug FROM posts WHERE image IS NULL')->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public function testFieldsFitSchema(): void
    {
        // В строгом режиме MySQL не обрезает длинное значение, а отклоняет вставку, и сидер упал бы ещё до тестов
        $mode = $this->select('SELECT @@sql_mode')->fetchColumn();

        self::assertIsString($mode);
        self::assertStringContainsString('STRICT_TRANS_TABLES', $mode);
    }
}
