<?php

declare(strict_types=1);

namespace App\Seed;

use App\Blog\Slug;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use PDO;
use RuntimeException;
use Throwable;

final class Seeder
{
    private const CATEGORY_KEYS = ['slug', 'name', 'description'];
    private const POST_KEYS = ['title', 'image', 'description', 'categories', 'published_at', 'views', 'content'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
        private readonly string $seedsDir,
        private readonly string $uploadsDir,
    ) {
    }

    /**
     * @return array{categories: int, posts: int}
     */
    public function run(): array
    {
        if ($this->categories->count() > 0) {
            throw new RuntimeException('В базе уже есть данные, для пересоздания запустите сидер с --fresh');
        }

        $categories = $this->loadCategories();
        $posts = $this->loadPosts();

        // Обложки копируются до вставки: если какой-то не хватает, база остается пустой
        $this->copyCovers(array_unique(array_column($posts, 'image')));

        $this->pdo->beginTransaction();
        try {
            $this->categories->insertMany($categories);
            $this->posts->insertMany(array_map(fn (array $post): array => [
                'title' => $post['title'],
                'slug' => $post['slug'],
                'image' => "/uploads/{$post['image']}",
                'description' => $post['description'],
                'content' => $post['content'],
                'views' => $post['views'],
                'published_at' => $post['published_at'],
            ], $posts));
            $this->posts->attachCategories($this->links($posts));
            $this->pdo->commit();
        } catch (Throwable $e) {
            // Если упало само соединение, транзакции уже нет, и rollBack() бросил бы своё исключение вместо исходного
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return ['categories' => count($categories), 'posts' => count($posts)];
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

        $categories = require $file;
        if (!is_array($categories) || $categories === []) {
            throw new RuntimeException("В {$file} нет списка категорий");
        }
        foreach ($categories as $category) {
            $this->requireKeys($category, self::CATEGORY_KEYS, $file);
        }

        /** @var list<array{slug: string, name: string, description: string}> $categories */
        foreach ($categories as $category) {
            $this->requireSlug($category['slug'], $file);
        }

        return $categories;
    }

    /**
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     image: string,
     *     description: string,
     *     categories: list<string>,
     *     published_at: string,
     *     views: int,
     *     content: string
     * }>
     */
    private function loadPosts(): array
    {
        $posts = [];
        foreach (glob($this->seedsDir . '/posts/*.php') ?: [] as $file) {
            /** @var array{title: string, image: string, description: string, categories: list<string>, published_at: string, views: int, content: string} $post */
            $post = require $file;
            $this->requireKeys($post, self::POST_KEYS, $file);
            if ($post['categories'] === []) {
                throw new RuntimeException("В {$file} пустой список категорий");
            }
            // Slug статьи - имя её файла
            $slug = basename($file, '.php');
            $this->requireSlug($slug, $file);
            $posts[] = ['slug' => $slug] + $post;
        }

        // Старые статьи получают меньшие id, как если бы их добавляли по мере публикации
        usort($posts, fn (array $a, array $b): int => [$a['published_at'], $a['slug']] <=> [$b['published_at'], $b['slug']]);

        return $posts;
    }

    /**
     * @param list<string> $keys
     */
    private function requireKeys(mixed $data, array $keys, string $file): void
    {
        $missing = is_array($data) ? array_diff($keys, array_keys($data)) : $keys;
        if ($missing !== []) {
            throw new RuntimeException("В {$file} нет полей: " . implode(', ', $missing));
        }
    }

    private function requireSlug(mixed $slug, string $file): void
    {
        if (!is_string($slug) || !Slug::isValid($slug)) {
            $value = is_string($slug) ? $slug : get_debug_type($slug);
            throw new RuntimeException(
                "В {$file} slug «{$value}» не подходит для адреса: нужны латиница в нижнем регистре, цифры и дефисы",
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
     * @param list<array{slug: string, categories: list<string>}> $posts
     * @return list<array{int, int, int}>
     */
    private function links(array $posts): array
    {
        $categoryIds = $this->categories->idsBySlug();
        $postIds = $this->posts->idsBySlug();

        $links = [];
        foreach ($posts as $post) {
            // Первая категория в файле статьи - основная
            foreach ($post['categories'] as $position => $slug) {
                $categoryId = $categoryIds[$slug]
                    ?? throw new RuntimeException("У статьи {$post['slug']} неизвестная категория {$slug}");
                $links[] = [$postIds[$post['slug']], $categoryId, $position];
            }
        }

        return $links;
    }
}
