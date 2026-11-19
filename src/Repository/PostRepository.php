<?php

declare(strict_types=1);

namespace App\Repository;

use App\Blog\Category;
use App\Blog\Post;
use App\Blog\PostCard;
use App\Blog\Slug;
use App\Blog\Sort;
use App\Database\Row;
use DateTimeImmutable;
use PDO;

final class PostRepository
{
    // Колонки для PostCard: в списках текст статьи не нужен. Основная категория - для заглушки вместо обложки:
    // подзапрос на строку списка идёт по UNIQUE (post_id, position) и первичному ключу категорий
    private const CARD_COLUMNS = 'p.id, p.slug, p.title, p.description, p.image, p.views, p.published_at,
        (SELECT mc.slug FROM category_post mcp JOIN categories mc ON mc.id = mcp.category_id
         WHERE mcp.post_id = p.id ORDER BY mcp.position LIMIT 1) AS main_category';
    // Похожие ищутся среди стольких последних статей каждой категории статьи: группировать всех соседей
    // на 100 000 статей - секунды
    private const SIMILAR_CANDIDATES = 50;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Последние статьи каждой непустой категории одним запросом, порядок категорий - по id, как в меню.
     *
     * @return list<array{category: Category, posts: list<PostCard>}>
     */
    public function latestByCategory(int $perCategory): array
    {
        // LATERAL берёт у каждой категории только id последних статей, пустые категории отсекает JOIN; колонки
        // карточки и подзапрос основной категории - снаружи, только для выбранных. STRAIGHT_JOIN: статьи идут
        // по индексу даты, связь проверяется по PK, и LIMIT обрывает проход. Сам MySQL начинает со связей
        // и сортирует все статьи категории - на 100 000 статей это 13 с вместо миллисекунд
        $statement = $this->pdo->prepare(
            'SELECT c.id AS category_id, c.slug AS category_slug, c.name AS category_name,
                    c.description AS category_description, ' . self::CARD_COLUMNS . '
             FROM categories c
             JOIN LATERAL (
                 SELECT recent.id FROM posts recent
                 STRAIGHT_JOIN category_post cp ON cp.post_id = recent.id
                 WHERE cp.category_id = c.id
                 ORDER BY recent.published_at DESC, recent.id DESC
                 LIMIT ?
             ) latest ON TRUE
             JOIN posts p ON p.id = latest.id
             ORDER BY c.id, p.published_at DESC, p.id DESC',
        );
        $statement->execute([$perCategory]);

        $sections = [];
        foreach (Row::all($statement) as $row) {
            $categoryId = $row->int('category_id');
            $sections[$categoryId] ??= [
                'category' => new Category(
                    $categoryId,
                    $row->string('category_slug'),
                    $row->string('category_name'),
                    $row->string('category_description'),
                ),
                'posts' => [],
            ];
            $sections[$categoryId]['posts'][] = $this->card($row);
        }

        return array_values($sections);
    }

    public function countInCategory(int $categoryId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) AS total FROM category_post WHERE category_id = ?');
        $statement->execute([$categoryId]);

        return Row::first($statement)?->int('total') ?? 0;
    }

    /**
     * Страница статей категории. При равных датах или просмотрах порядок задаёт id,
     * иначе такие статьи могли бы переезжать между страницами от запроса к запросу.
     *
     * @return list<PostCard>
     */
    public function inCategory(int $categoryId, Sort $sort, int $limit, int $offset): array
    {
        // ORDER BY не принимает параметры запроса, поэтому сортировка выбирается из готовых строк
        $order = match ($sort) {
            Sort::Date => 'p.published_at DESC, p.id DESC',
            Sort::Views => 'p.views DESC, p.id DESC',
        };

        $statement = $this->pdo->prepare(
            'SELECT ' . self::CARD_COLUMNS . " FROM posts p
             JOIN category_post cp ON cp.post_id = p.id
             WHERE cp.category_id = ?
             ORDER BY {$order}
             LIMIT ? OFFSET ?",
        );
        $statement->execute([$categoryId, $limit, $offset]);

        return array_map($this->card(...), Row::all($statement));
    }

    public function findBySlug(string $slug): ?Post
    {
        // Строка не в формате slug не может быть в базе, а запрос с ней MySQL отклонил бы с ошибкой
        if (!Slug::isValid($slug)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, slug, title, description, image, views, published_at, content FROM posts WHERE slug = ?',
        );
        $statement->execute([$slug]);
        $row = Row::first($statement);

        return $row === null ? null : $this->post($row);
    }

    /**
     * Счётчик увеличивает сама база, поэтому одновременные просмотры не теряются.
     */
    public function incrementViews(int $postId): void
    {
        $this->pdo->prepare('UPDATE posts SET views = views + 1 WHERE id = ?')->execute([$postId]);
    }

    /**
     * Похожие статьи: сначала те, у кого больше общих категорий со статьёй, среди равных - новые.
     * Меньше $limit - только если во всём блоге не хватает статей.
     *
     * @return list<PostCard>
     */
    public function similar(int $postId, int $limit): array
    {
        $similar = $this->byCommonCategories($postId, $limit);
        if (count($similar) === $limit) {
            return $similar;
        }

        // Добор отдельным запросом, а не LEFT JOIN со всеми статьями: он нужен редко и идёт по индексу даты с LIMIT
        $exclude = [$postId, ...array_map(fn (PostCard $post): int => $post->id, $similar)];

        return [...$similar, ...$this->latestExcept($exclude, $limit - count($similar))];
    }

    /**
     * @return list<PostCard>
     */
    public function latest(int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::CARD_COLUMNS . ' FROM posts p
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT ?',
        );
        $statement->execute([$limit]);

        return array_map($this->card(...), Row::all($statement));
    }

    /**
     * Даты публикации всех статей, от новых к старым. В один файл sitemap помещается 50 000 адресов:
     * блогу до этого далеко, а больше понадобился бы индекс из нескольких файлов.
     *
     * @return array<string, DateTimeImmutable> slug статьи => дата
     */
    public function publishedDates(): array
    {
        $statement = $this->pdo->prepare('SELECT slug, published_at FROM posts ORDER BY published_at DESC, id DESC');
        $statement->execute();

        $dates = [];
        foreach (Row::all($statement) as $row) {
            $dates[$row->string('slug')] = $row->date('published_at');
        }

        return $dates;
    }

    /**
     * @param list<string> $slugs
     * @return array<string, int>
     */
    public function idsBySlug(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
        $statement = $this->pdo->prepare("SELECT slug, id FROM posts WHERE slug IN ({$placeholders})");
        $statement->execute($slugs);

        $ids = [];
        foreach (Row::all($statement) as $row) {
            $ids[$row->string('slug')] = $row->int('id');
        }

        return $ids;
    }

    /**
     * @param list<array{
     *     title: string,
     *     slug: string,
     *     image: ?string,
     *     description: string,
     *     content: string,
     *     views: int,
     *     published_at: string
     * }> $posts
     */
    public function insertMany(array $posts): void
    {
        if ($posts === []) {
            return;
        }

        $params = [];
        foreach ($posts as $post) {
            array_push(
                $params,
                $post['title'],
                $post['slug'],
                $post['image'],
                $post['description'],
                $post['content'],
                $post['views'],
                $post['published_at'],
            );
        }

        $rows = implode(', ', array_fill(0, count($posts), '(?, ?, ?, ?, ?, ?, ?)'));
        $this->pdo->prepare(
            "INSERT INTO posts (title, slug, image, description, content, views, published_at) VALUES {$rows}",
        )->execute($params);
    }

    /**
     * @param list<array{int, int, int}> $links тройки [id статьи, id категории, место категории у статьи]
     */
    public function attachCategories(array $links): void
    {
        if ($links === []) {
            return;
        }

        $rows = implode(', ', array_fill(0, count($links), '(?, ?, ?)'));
        $this->pdo->prepare("INSERT INTO category_post (post_id, category_id, position) VALUES {$rows}")
            ->execute(array_merge(...$links));
    }

    /**
     * @return list<PostCard>
     */
    private function byCommonCategories(int $postId, int $limit): array
    {
        // own - категории статьи, other - последние статьи каждой из них, кроме самой статьи (STRAIGHT_JOIN -
        // как на главной); COUNT(*) в группе - число общих категорий среди этих кандидатов.
        // Остальные поля p можно выбирать при GROUP BY p.id: они зависят от первичного ключа
        $statement = $this->pdo->prepare(
            'SELECT ' . self::CARD_COLUMNS . ' FROM category_post own
             JOIN LATERAL (
                 SELECT candidate.id FROM posts candidate
                 STRAIGHT_JOIN category_post cp ON cp.post_id = candidate.id
                 WHERE cp.category_id = own.category_id AND candidate.id <> own.post_id
                 ORDER BY candidate.published_at DESC, candidate.id DESC
                 LIMIT ' . self::SIMILAR_CANDIDATES . '
             ) other ON TRUE
             JOIN posts p ON p.id = other.id
             WHERE own.post_id = ?
             GROUP BY p.id
             ORDER BY COUNT(*) DESC, p.published_at DESC, p.id DESC
             LIMIT ?',
        );
        $statement->execute([$postId, $limit]);

        return array_map($this->card(...), Row::all($statement));
    }

    /**
     * @param non-empty-list<int> $exclude
     * @return list<PostCard>
     */
    private function latestExcept(array $exclude, int $limit): array
    {
        $placeholders = implode(', ', array_fill(0, count($exclude), '?'));
        $statement = $this->pdo->prepare(
            'SELECT ' . self::CARD_COLUMNS . " FROM posts p
             WHERE p.id NOT IN ({$placeholders})
             ORDER BY p.published_at DESC, p.id DESC
             LIMIT ?",
        );
        $statement->execute([...$exclude, $limit]);

        return array_map($this->card(...), Row::all($statement));
    }

    private function card(Row $row): PostCard
    {
        return new PostCard(
            id: $row->int('id'),
            slug: $row->string('slug'),
            title: $row->string('title'),
            description: $row->string('description'),
            image: $row->nullableString('image'),
            views: $row->int('views'),
            publishedAt: $row->date('published_at'),
            mainCategorySlug: $row->nullableString('main_category'),
        );
    }

    private function post(Row $row): Post
    {
        return new Post(
            id: $row->int('id'),
            slug: $row->string('slug'),
            title: $row->string('title'),
            description: $row->string('description'),
            image: $row->nullableString('image'),
            views: $row->int('views'),
            publishedAt: $row->date('published_at'),
            content: $row->string('content'),
        );
    }
}
