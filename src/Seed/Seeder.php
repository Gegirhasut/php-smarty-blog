<?php

declare(strict_types=1);

namespace App\Seed;

use App\Blog\Slug;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use PDO;
use RuntimeException;
use Throwable;

/**
 * @phpstan-type SeedPost array{
 *     slug: string,
 *     title: string,
 *     image?: string,
 *     description: string,
 *     categories: list<string>,
 *     published_at: string,
 *     views: int,
 *     content: string
 * }
 */
final class Seeder
{
    private const CATEGORY_KEYS = ['slug', 'name', 'description'];
    // Обложка (image) необязательна
    private const POST_KEYS = ['title', 'description', 'categories', 'published_at', 'views', 'content'];
    // 1000 статей - около 3 МБ запроса и 7000 параметров: далеко от max_allowed_packet и предела в 65 535
    private const BATCH = 1000;

    public function __construct(
        private readonly PDO $pdo,
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
        private readonly string $seedsDir,
        private readonly string $uploadsDir,
    ) {
    }

    /**
     * @param int $generate сколько статей сгенерировать в дополнение к демо-данным (PostGenerator)
     * @return array{categories: int, posts: int}
     */
    public function run(int $generate = 0): array
    {
        if ($this->categories->count() > 0) {
            throw new RuntimeException('В базе уже есть данные, для пересоздания запустите сидер с --fresh');
        }

        $categories = $this->loadCategories();
        $posts = $this->loadPosts();
        $generator = $generate > 0 ? new PostGenerator($posts, array_column($categories, 'slug'), $generate) : null;

        // Обложки копируются до вставки: если какой-то не хватает, база остается пустой.
        // Сгенерированные статьи берут обложки демо-статей, отдельно их копировать не нужно
        $this->copyCovers(array_unique(array_column($posts, 'image')));

        $this->pdo->beginTransaction();
        try {
            $this->categories->insertMany($categories);
            $categoryIds = $this->categories->idsBySlug();
            // Сгенерированный архив старше демо-статей и вставляется первым: id растут вместе с датой
            if ($generator !== null) {
                for ($from = 0; $from < $generate; $from += self::BATCH) {
                    $this->insertPosts($generator->posts(min(self::BATCH, $generate - $from)), $categoryIds);
                }
            }
            $this->insertPosts($posts, $categoryIds);
            $this->pdo->commit();
        } catch (Throwable $e) {
            // Если упало само соединение, транзакции уже нет, и rollBack() бросил бы своё исключение вместо исходного
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return ['categories' => count($categories), 'posts' => count($posts) + $generate];
    }

    /**
     * @return list<array{slug: string, name: string, description: string}>
     */
    private function loadCategories(): array
    {
        $file = $this->seedsDir . '/categories.php';
        if (!is_file($file)) {
            throw new RuntimeException("Нет файла с данными {$file}");
        }

        $data = require $file;
        if (!is_array($data) || $data === []) {
            throw new RuntimeException("В {$file} нет списка категорий");
        }

        $categories = [];
        foreach ($data as $category) {
            $category = $this->fields($category, self::CATEGORY_KEYS, $file);
            $slug = $this->string($category, 'slug', $file);
            $this->requireSlug($slug, $file);
            $categories[] = [
                'slug' => $slug,
                'name' => $this->string($category, 'name', $file),
                'description' => $this->string($category, 'description', $file),
            ];
        }

        return $categories;
    }

    /**
     * @return list<SeedPost>
     */
    private function loadPosts(): array
    {
        $posts = [];
        foreach (glob($this->seedsDir . '/posts/*.php') ?: [] as $file) {
            $data = $this->fields(require $file, self::POST_KEYS, $file);
            // Slug статьи - имя её файла
            $slug = basename($file, '.php');
            $this->requireSlug($slug, $file);
            $post = [
                'slug' => $slug,
                'title' => $this->string($data, 'title', $file),
                'description' => $this->string($data, 'description', $file),
                'categories' => $this->categorySlugs($data, $file),
                'published_at' => $this->string($data, 'published_at', $file),
                'views' => $this->int($data, 'views', $file),
                'content' => $this->string($data, 'content', $file),
            ];
            if (array_key_exists('image', $data)) {
                $post['image'] = $this->string($data, 'image', $file);
            }
            $posts[] = $post;
        }

        // Старые статьи получают меньшие id, как если бы их добавляли по мере публикации
        usort($posts, fn (array $a, array $b): int => [$a['published_at'], $a['slug']] <=> [$b['published_at'], $b['slug']]);

        return $posts;
    }

    /**
     * @param list<string> $keys
     * @return array<mixed>
     */
    private function fields(mixed $data, array $keys, string $file): array
    {
        $missing = is_array($data) ? array_diff($keys, array_keys($data)) : $keys;
        if (!is_array($data) || $missing !== []) {
            throw new RuntimeException("В {$file} нет полей: " . implode(', ', $missing));
        }

        return $data;
    }

    /**
     * @param array<mixed> $data
     */
    private function string(array $data, string $key, string $file): string
    {
        return is_string($data[$key])
            ? $data[$key]
            : throw new RuntimeException("В {$file} поле {$key} должно быть строкой, а не " . get_debug_type($data[$key]));
    }

    /**
     * @param array<mixed> $data
     */
    private function int(array $data, string $key, string $file): int
    {
        return is_int($data[$key])
            ? $data[$key]
            : throw new RuntimeException("В {$file} поле {$key} должно быть целым числом, а не " . get_debug_type($data[$key]));
    }

    /**
     * @param array<mixed> $data
     * @return list<string>
     */
    private function categorySlugs(array $data, string $file): array
    {
        $slugs = $data['categories'];
        if ($slugs === []) {
            throw new RuntimeException("В {$file} пустой список категорий");
        }
        if (!is_array($slugs) || !array_is_list($slugs)) {
            throw new RuntimeException("В {$file} поле categories должно быть списком slug категорий");
        }

        $list = [];
        foreach ($slugs as $slug) {
            $list[] = is_string($slug)
                ? $slug
                : throw new RuntimeException("В {$file} поле categories должно быть списком slug категорий");
        }

        return $list;
    }

    private function requireSlug(string $slug, string $file): void
    {
        if (!Slug::isValid($slug)) {
            throw new RuntimeException(
                "В {$file} slug «{$slug}» не подходит для адреса: нужны латиница в нижнем регистре, цифры и дефисы, "
                . 'и не одни цифры',
            );
        }
    }

    /**
     * @param array<string> $files
     */
    private function copyCovers(array $files): void
    {
        // mkdir() и copy() при ошибке только предупреждают, а сидер должен остановиться с понятным сообщением
        if (!is_dir($this->uploadsDir) && !@mkdir($this->uploadsDir, 0775, true)) {
            throw new RuntimeException("Не удалось создать папку {$this->uploadsDir}");
        }

        foreach ($files as $file) {
            if (!@copy("{$this->seedsDir}/images/{$file}", "{$this->uploadsDir}/{$file}")) {
                throw new RuntimeException("Не удалось скопировать обложку {$file} в {$this->uploadsDir}");
            }
        }
    }

    /**
     * @param list<SeedPost> $posts
     * @param array<string, int> $categoryIds
     */
    private function insertPosts(array $posts, array $categoryIds): void
    {
        $this->posts->insertMany(array_map(fn (array $post): array => [
            'title' => $post['title'],
            'slug' => $post['slug'],
            'image' => isset($post['image']) ? "/uploads/{$post['image']}" : null,
            'description' => $post['description'],
            'content' => $post['content'],
            'views' => $post['views'],
            'published_at' => $post['published_at'],
        ], $posts));
        $this->posts->attachCategories($this->links($posts, $categoryIds));
    }

    /**
     * @param list<SeedPost> $posts
     * @param array<string, int> $categoryIds
     * @return list<array{int, int, int}>
     */
    private function links(array $posts, array $categoryIds): array
    {
        $postIds = $this->posts->idsBySlug(array_column($posts, 'slug'));

        $links = [];
        foreach ($posts as $post) {
            // Первая категория в списке статьи - основная
            foreach ($post['categories'] as $position => $slug) {
                $categoryId = $categoryIds[$slug]
                    ?? throw new RuntimeException("У статьи {$post['slug']} неизвестная категория {$slug}");
                $links[] = [$postIds[$post['slug']], $categoryId, $position];
            }
        }

        return $links;
    }
}
