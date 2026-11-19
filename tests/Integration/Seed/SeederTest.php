<?php

declare(strict_types=1);

namespace App\Tests\Integration\Seed;

use App\Seed\Seeder;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Tests\DatabaseTestCase;
use App\Tests\TempDir;
use PDO;
use PDOException;
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

    public function testLinksCopyDateAndViewsOfPost(): void
    {
        // Демо-статьи и сгенерированные: ленты категорий сортируются по этим копиям
        $this->seeder(self::FIXTURES)->run(20);

        self::assertGreaterThan(23, $this->select('SELECT COUNT(*) FROM category_post')->fetchColumn());
        self::assertSame(0, $this->select(
            'SELECT COUNT(*) FROM category_post cp JOIN posts p ON p.id = cp.post_id
             WHERE cp.published_at <> p.published_at OR cp.views <> p.views',
        )->fetchColumn());
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

    public function testGeneratesPostsInBatchesBeforeDemoPosts(): void
    {
        // Больше одной пачки: последняя неполная
        $result = $this->seeder(self::FIXTURES)->run(1001);

        self::assertSame(['categories' => 2, 'posts' => 1004], $result);
        $byId = $this->select('SELECT slug FROM posts ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        // Архив старше демо-статей и вставлен раньше них: id растут вместе с датой
        self::assertSame($this->select('SELECT slug FROM posts ORDER BY published_at, id')->fetchAll(PDO::FETCH_COLUMN), $byId);
        self::assertSame(['pasta', 'baikal', 'kazan'], array_slice($byId, -3));
        self::assertSame(
            [1, 2],
            $this->select(
                'SELECT DISTINCT COUNT(*) AS links FROM category_post GROUP BY post_id ORDER BY links',
            )->fetchAll(PDO::FETCH_COLUMN),
        );
        self::assertSame(
            ['/uploads/food-1.webp', '/uploads/travel-1.webp', '/uploads/travel-2.webp'],
            $this->select('SELECT DISTINCT image FROM posts ORDER BY image')->fetchAll(PDO::FETCH_COLUMN),
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

    public function testFreshReseedsFilledDatabase(): void
    {
        $seeder = $this->seeder(self::FIXTURES);
        $seeder->run();

        self::assertSame(['categories' => 2, 'posts' => 3], $seeder->run(fresh: true));
        $this->assertRowCount(2, 'categories');
        $this->assertRowCount(3, 'posts');
    }

    /**
     * @return iterable<string, array{callable(string): mixed, string}>
     */
    public static function brokenSeeds(): iterable
    {
        yield 'статья без полей' => [
            fn (string $dir) => file_put_contents("{$dir}/posts/broken.php", "<?php return ['title' => 'Статья'];"),
            'broken.php нет полей',
        ];
        yield 'неизвестная категория' => [
            fn (string $dir) => file_put_contents(
                "{$dir}/posts/broken.php",
                "<?php return ['title' => 'Статья', 'description' => '', 'categories' => ['travel', 'missing'],"
                . " 'published_at' => '2026-10-01 10:00:00', 'views' => 1, 'content' => ''];",
            ),
            'broken.php неизвестная категория missing',
        ];
        yield 'нет обложки' => [fn (string $dir) => unlink("{$dir}/images/food-1.webp"), 'food-1.webp'];
    }

    /**
     * @param callable(string): mixed $break
     */
    #[DataProvider('brokenSeeds')]
    public function testFreshKeepsDatabaseWhenSeedsAreBroken(callable $break, string $message): void
    {
        $this->seeder(self::FIXTURES)->run();
        $seeds = new TempDir();
        $seeds->copy(self::FIXTURES, 'seeds');
        $break($seeds->path . '/seeds');

        try {
            $this->seeder($seeds->path . '/seeds')->run(fresh: true);
            self::fail('Сидер должен был остановиться на ошибке в данных');
        } catch (RuntimeException $e) {
            self::assertStringContainsString($message, $e->getMessage());
        } finally {
            $seeds->remove();
        }

        // Данные проверяются до пересоздания таблиц: база осталась как была
        $this->assertRowCount(2, 'categories');
        $this->assertRowCount(3, 'posts');
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

    public function testValidatesCategoryFieldTypes(): void
    {
        $seeds = new TempDir();
        $seeds->copy(self::FIXTURES, 'seeds');
        file_put_contents(
            $seeds->path . '/seeds/categories.php',
            "<?php return [['slug' => 'travel', 'name' => ['Путешествия'], 'description' => '']];",
        );

        try {
            $this->seeder($seeds->path . '/seeds')->run();
            self::fail('Сидер должен был остановиться на названии не строкой');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('categories.php поле name должно быть строкой, а не array', $e->getMessage());
        } finally {
            $seeds->remove();
        }

        $this->assertRowCount(0, 'categories');
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

        yield 'не массив' => ["<?php return 'Статья';", 'нет полей: title, description'];
        yield 'без даты' => ["<?php return ['title' => 'Статья', 'description' => '', 'categories' => ['travel'], 'views' => 1, 'content' => ''];", 'нет полей: published_at'];
        yield 'без категорий' => ["<?php return [{$fields}, 'categories' => [], 'image' => 'travel-1.webp'];", 'пустой список категорий'];
        yield 'категории строкой' => ["<?php return [{$fields}, 'categories' => 'travel'];", 'поле categories должно быть списком'];
        yield 'категория не строка' => ["<?php return [{$fields}, 'categories' => ['travel', 7]];", 'поле categories должно быть списком'];
        yield 'категории не список' => ["<?php return [{$fields}, 'categories' => ['main' => 'travel']];", 'поле categories должно быть списком'];
        yield 'заголовок не строка' => ["<?php return [{$fields}, 'categories' => ['travel'], 'title' => null];", 'поле title должно быть строкой, а не null'];
        yield 'просмотры строкой' => ["<?php return [{$fields}, 'categories' => ['travel'], 'views' => '5'];", 'поле views должно быть целым числом'];
        yield 'обложка null' => ["<?php return [{$fields}, 'categories' => ['travel'], 'image' => null];", 'поле image должно быть строкой'];
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

    public function testPostWithoutCover(): void
    {
        $seeds = new TempDir();
        $seeds->copy(self::FIXTURES, 'seeds');
        file_put_contents(
            $seeds->path . '/seeds/posts/bare.php',
            "<?php return ['title' => 'Без обложки', 'description' => '', 'categories' => ['travel'],"
            . " 'published_at' => '2026-10-01 10:00:00', 'views' => 1, 'content' => ''];",
        );

        try {
            $this->seeder($seeds->path . '/seeds')->run();
        } finally {
            $seeds->remove();
        }

        self::assertNull($this->select("SELECT image FROM posts WHERE slug = 'bare'")->fetchColumn());
        self::assertSame('/uploads/travel-1.webp', $this->select("SELECT image FROM posts WHERE slug = 'baikal'")->fetchColumn());
    }

    public function testRollsBackEverythingOnDatabaseError(): void
    {
        $seeds = new TempDir();
        $seeds->copy(self::FIXTURES, 'seeds');
        // Файл проходит проверку сидера, а заголовок длиннее колонки отклоняет MySQL - уже после вставки категорий
        $title = str_repeat('я', 201);
        file_put_contents(
            $seeds->path . '/seeds/posts/long.php',
            "<?php return ['title' => '{$title}', 'description' => '', 'categories' => ['travel'],"
            . " 'published_at' => '2026-10-01 10:00:00', 'views' => 1, 'content' => ''];",
        );

        try {
            $this->seeder($seeds->path . '/seeds')->run();
            self::fail('Сидер должен был остановиться на ошибке базы');
        } catch (PDOException $e) {
            self::assertStringContainsString('title', $e->getMessage());
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
            $this->schema,
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
            $this->schema,
            new CategoryRepository($this->pdo),
            new PostRepository($this->pdo),
            $seedsDir,
            $this->uploads->path . '/uploads',
        );
    }
}
