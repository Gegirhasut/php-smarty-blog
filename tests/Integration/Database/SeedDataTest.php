<?php

declare(strict_types=1);

namespace App\Tests\Integration\Database;

use App\Database\Seeder;
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
    private TempDir $uploads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploads = new TempDir();

        (new Seeder(
            $this->pdo,
            new CategoryRepository($this->pdo),
            new PostRepository($this->pdo),
            dirname(__DIR__, 3) . '/database/seeds',
            $this->uploads->path,
        ))->run();
    }

    protected function tearDown(): void
    {
        $this->uploads->remove();
        parent::tearDown();
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
        $images = $this->select('SELECT DISTINCT image FROM posts')->fetchAll(PDO::FETCH_COLUMN);

        self::assertCount(24, $images);
        foreach ($images as $image) {
            self::assertIsString($image);
            self::assertFileExists($this->uploads->path . '/' . basename($image));
        }
    }

    public function testFieldsFitSchema(): void
    {
        // В строгом режиме MySQL не обрезает длинное значение, а отклоняет вставку, и сидер в setUp() упал бы
        $mode = $this->select('SELECT @@sql_mode')->fetchColumn();

        self::assertIsString($mode);
        self::assertStringContainsString('STRICT_TRANS_TABLES', $mode);
    }
}
