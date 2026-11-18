<?php

declare(strict_types=1);

namespace App\Tests\Integration\Database;

use App\Database\Seeder;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Tests\DatabaseTestCase;
use App\Tests\TempDir;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class SeederTest extends DatabaseTestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/seeds';

    private TempDir $uploads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploads = new TempDir();
    }

    protected function tearDown(): void
    {
        $this->uploads->remove();
        parent::tearDown();
    }

    public function testSeedsCategoriesPostsAndLinks(): void
    {
        $result = $this->seeder(self::FIXTURES)->run();

        self::assertSame(['categories' => 2, 'posts' => 3], $result);
        self::assertSame(
            ['baikal' => 'travel', 'kazan' => 'food,travel', 'pasta' => 'food'],
            $this->select(
                'SELECT p.slug, GROUP_CONCAT(c.slug ORDER BY c.slug) FROM posts p
                 JOIN category_post cp ON cp.post_id = p.id
                 JOIN categories c ON c.id = cp.category_id
                 GROUP BY p.slug ORDER BY p.slug',
            )->fetchAll(PDO::FETCH_KEY_PAIR),
        );
    }

    public function testFirstCategoryOfPostIsMain(): void
    {
        $this->seeder(self::FIXTURES)->run();

        // В kazan.php первой указана food, хотя по id раньше travel
        self::assertSame(
            ['food' => 0, 'travel' => 1],
            $this->select(
                "SELECT c.slug, cp.position FROM category_post cp
                 JOIN categories c ON c.id = cp.category_id
                 JOIN posts p ON p.id = cp.post_id
                 WHERE p.slug = 'kazan'
                 ORDER BY cp.position",
            )->fetchAll(PDO::FETCH_KEY_PAIR),
        );
    }

    public function testStoresPostFields(): void
    {
        $this->seeder(self::FIXTURES)->run();

        $post = $this->select("SELECT title, image, views, published_at, content FROM posts WHERE slug = 'baikal'")->fetch();

        self::assertSame([
            'title' => 'Осенний Байкал',
            'image' => '/uploads/travel-1.webp',
            'views' => 1200,
            'published_at' => '2026-09-10 10:00:00',
            'content' => "Вступление.\n\n## Ольхон\n\nТекст раздела.",
        ], $post);
    }

    public function testCopiesUsedCoversToUploads(): void
    {
        $this->seeder(self::FIXTURES)->run();

        foreach (['food-1', 'travel-1', 'travel-2'] as $cover) {
            self::assertFileExists("{$this->uploads->path}/uploads/{$cover}.webp");
        }
    }

    public function testOlderPostsGetSmallerIds(): void
    {
        $this->seeder(self::FIXTURES)->run();

        self::assertSame(
            ['pasta', 'baikal', 'kazan'],
            $this->select('SELECT slug FROM posts ORDER BY id')->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public function testRefusesToSeedFilledDatabase(): void
    {
        $seeder = $this->seeder(self::FIXTURES);
        $seeder->run();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--fresh');

        $seeder->run();
    }

    public function testRequiresDataFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('categories.php');

        $this->seeder(__DIR__ . '/missing')->run();
    }

    public function testValidatesCategoriesFile(): void
    {
        $seeds = new TempDir();
        $seeds->copy(self::FIXTURES, 'seeds');
        file_put_contents($seeds->path . '/seeds/categories.php', "<?php return [['slug' => 'travel']];");

        try {
            $this->seeder($seeds->path . '/seeds')->run();
            self::fail('Сидер должен был остановиться на неполной категории');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('categories.php нет полей: name, description', $e->getMessage());
        } finally {
            $seeds->remove();
        }
    }

    public function testRejectsCategorySlugUnfitForAddress(): void
    {
        $seeds = new TempDir();
        $seeds->copy(self::FIXTURES, 'seeds');
        file_put_contents(
            $seeds->path . '/seeds/categories.php',
            "<?php return [['slug' => 'Путешествия', 'name' => 'Путешествия', 'description' => '']];",
        );

        try {
            $this->seeder($seeds->path . '/seeds')->run();
            self::fail('Сидер должен был остановиться на slug с кириллицей');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('categories.php slug «Путешествия» не подходит для адреса', $e->getMessage());
        } finally {
            $seeds->remove();
        }

        $this->assertRowCount(0, 'categories');
    }

    public function testRejectsPostFileNameUnfitForAddress(): void
    {
        $seeds = new TempDir();
        $seeds->copy(self::FIXTURES, 'seeds');
        rename($seeds->path . '/seeds/posts/pasta.php', $seeds->path . '/seeds/posts/Pasta.php');

        try {
            $this->seeder($seeds->path . '/seeds')->run();
            self::fail('Сидер должен был остановиться на slug в верхнем регистре');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Pasta.php slug «Pasta» не подходит для адреса', $e->getMessage());
        } finally {
            $seeds->remove();
        }

        $this->assertRowCount(0, 'posts');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function brokenPosts(): iterable
    {
        $fields = "'title' => 'Статья', 'description' => '', 'published_at' => '2026-10-01 10:00:00', 'views' => 1, 'content' => ''";

        yield 'не массив' => ["<?php return 'Статья';", 'нет полей: title, image'];
        yield 'без обложки' => ["<?php return [{$fields}, 'categories' => ['travel']];", 'нет полей: image'];
        yield 'без категорий' => ["<?php return [{$fields}, 'categories' => [], 'image' => 'travel-1.webp'];", 'пустой список категорий'];
    }

    #[DataProvider('brokenPosts')]
    public function testValidatesPostFile(string $code, string $message): void
    {
        $seeds = new TempDir();
        $seeds->copy(self::FIXTURES, 'seeds');
        file_put_contents($seeds->path . '/seeds/posts/broken.php', $code);

        try {
            $this->seeder($seeds->path . '/seeds')->run();
            self::fail('Сидер должен был остановиться на неполной статье');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('broken.php', $e->getMessage());
            self::assertStringContainsString($message, $e->getMessage());
        } finally {
            $seeds->remove();
        }

        $this->assertRowCount(0, 'posts');
    }

    public function testRollsBackEverythingOnUnknownCategory(): void
    {
        $seeds = new TempDir();
        copy(self::FIXTURES . '/categories.php', $seeds->path . '/categories.php');
        mkdir($seeds->path . '/posts');
        mkdir($seeds->path . '/images');
        touch($seeds->path . '/images/travel-1.webp');
        file_put_contents(
            $seeds->path . '/posts/broken.php',
            "<?php return ['title' => 'Статья', 'description' => '', 'categories' => ['travel', 'missing'],"
            . " 'image' => 'travel-1.webp',"
            . " 'published_at' => '2026-10-01 10:00:00', 'views' => 1, 'content' => ''];",
        );

        try {
            $this->seeder($seeds->path)->run();
            self::fail('Сидер должен был остановиться на неизвестной категории');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('missing', $e->getMessage());
        } finally {
            $seeds->remove();
        }

        $this->assertRowCount(0, 'categories');
        $this->assertRowCount(0, 'posts');
    }

    public function testRequiresCoverFiles(): void
    {
        $seeds = new TempDir();
        $seeds->copy(self::FIXTURES, 'seeds');
        unlink($seeds->path . '/seeds/images/food-1.webp');

        try {
            $this->seeder($seeds->path . '/seeds')->run();
            self::fail('Сидер должен был остановиться без обложки');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('food-1.webp', $e->getMessage());
        } finally {
            $seeds->remove();
        }

        $this->assertRowCount(0, 'posts');
    }

    public function testFailsWhenUploadsCannotBeCreated(): void
    {
        touch($this->uploads->path . '/file');
        $seeder = new Seeder(
            $this->pdo,
            new CategoryRepository($this->pdo),
            new PostRepository($this->pdo),
            self::FIXTURES,
            $this->uploads->path . '/file/uploads',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Не удалось создать папку');

        $seeder->run();
    }

    private function seeder(string $seedsDir): Seeder
    {
        return new Seeder(
            $this->pdo,
            new CategoryRepository($this->pdo),
            new PostRepository($this->pdo),
            $seedsDir,
            $this->uploads->path . '/uploads',
        );
    }
}
