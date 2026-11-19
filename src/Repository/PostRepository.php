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

/**
 * Статьи категории, главная и похожие выбирают id по индексам связки category_post, где есть копии даты
 * и просмотров статьи, и только потом читают строки posts - для выбранных статей.
 */
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
        // LATERAL берёт у каждой категории id последних статей обратным проходом по индексу (category_id,
        // published_at, post_id): LIMIT читает ровно столько записей, сколько нужно, у пустой категории - ни одной,
        // и её отсекает JOIN. Колонки карточки и подзапрос основной категории - снаружи, только для выбранных статей.
        // category_id в ORDER BY внутри одной категории ничего не меняет, но без него MySQL не видит, что индекс
        // уже упорядочен: категорию из внешней таблицы он не считает постоянной и сортирует все её статьи
        $statement = $this->pdo->prepare(
            'SELECT c.id AS category_id, c.slug AS category_slug, c.name AS category_name,
                    c.description AS category_description, ' . self::CARD_COLUMNS . '
             FROM categories c
             JOIN LATERAL (
                 SELECT cp.post_id FROM category_post cp
                 WHERE cp.category_id = c.id
                 ORDER BY cp.category_id DESC, cp.published_at DESC, cp.post_id DESC
                 LIMIT ?
             ) latest ON TRUE
             JOIN posts p ON p.id = latest.post_id
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
        // ORDER BY не принимает параметры запроса, поэтому колонка выбирается из готовых строк
        $column = match ($sort) {
            Sort::Date => 'published_at',
            Sort::Views => 'views',
        };

        // Отложенное соединение: id страницы выбираются по покрывающему индексу связки, OFFSET пропускает только
        // его записи, а строки статей читаются для выбранных. Порядок снаружи - по той же копии из связки
        $statement = $this->pdo->prepare(
            'SELECT ' . self::CARD_COLUMNS . " FROM (
                 SELECT cp.post_id, cp.{$column} AS sort_value FROM category_post cp
                 WHERE cp.category_id = ?
                 ORDER BY cp.{$column} DESC, cp.post_id DESC
                 LIMIT ? OFFSET ?
             ) page
             JOIN posts p ON p.id = page.post_id
             ORDER BY page.sort_value DESC, page.post_id DESC",
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
     * Счётчик увеличивает сама база, поэтому одновременные просмотры не теряются. Статья и копии её просмотров
     * в связке меняются одним UPDATE: он атомарен, как любой запрос InnoDB, и не расходится с posts, а отдельная
     * транзакция из двух запросов была бы длиннее и не открылась бы внутри уже начатой.
     */
    public function incrementViews(int $postId): void
    {
        // UPDATE по нескольким таблицам меняет каждую строку один раз, сколько бы раз она ни встретилась в JOIN:
        // статья из трёх категорий получает +1, а не +3. LEFT JOIN - чтобы просмотр засчитался и статье без категорий
        $this->pdo->prepare(
            'UPDATE posts p LEFT JOIN category_post cp ON cp.post_id = p.id
             SET p.views = p.views + 1, cp.views = cp.views + 1
             WHERE p.id = ?',
        )->execute([$postId]);
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
     * Дата самой свежей статьи блога; null - статей нет.
     */
    public function newestPublishedAt(): ?DateTimeImmutable
    {
        $statement = $this->pdo->prepare('SELECT published_at FROM posts ORDER BY published_at DESC LIMIT 1');
        $statement->execute();

        return Row::first($statement)?->date('published_at');
    }

    /**
     * Наибольший id статьи, 0 - статей нет: по нему считаются файлы sitemap.
     */
    public function lastId(): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM posts ORDER BY id DESC LIMIT 1');
        $statement->execute();

        return Row::first($statement)?->int('id') ?? 0;
    }

    /**
     * Даты публикации статей с id от $firstId до $lastId, по возрастанию id - для файла sitemap. Диапазон идёт
     * по покрывающему индексу posts_sitemap_index, строки статей с текстом не читаются.
     *
     * @return array<string, DateTimeImmutable> slug статьи => дата
     */
    public function publishedDatesBetweenIds(int $firstId, int $lastId): array
    {
        $statement = $this->pdo->prepare('SELECT slug, published_at FROM posts WHERE id BETWEEN ? AND ? ORDER BY id');
        $statement->execute([$firstId, $lastId]);

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
     * Связи статей с категориями. Дата и просмотры - копии полей статьи: по ним сортируются ленты категорий.
     *
     * @param list<array{post_id: int, category_id: int, position: int, published_at: string, views: int}> $links
     *     position - место категории у статьи, 0 - основная
     */
    public function attachCategories(array $links): void
    {
        if ($links === []) {
            return;
        }

        $params = [];
        foreach ($links as $link) {
            array_push($params, $link['post_id'], $link['category_id'], $link['position'], $link['published_at'], $link['views']);
        }

        $rows = implode(', ', array_fill(0, count($links), '(?, ?, ?, ?, ?)'));
        $this->pdo->prepare(
            "INSERT INTO category_post (post_id, category_id, position, published_at, views) VALUES {$rows}",
        )->execute($params);
    }

    /**
     * @return list<PostCard>
     */
    private function byCommonCategories(int $postId, int $limit): array
    {
        // own - категории статьи, other - последние статьи каждой из них, кроме самой статьи, тем же обратным
        // проходом по индексу связки, что на главной (и с category_id в ORDER BY по той же причине); COUNT(*)
        // в группе - число общих категорий среди этих кандидатов.
        // Остальные поля p можно выбирать при GROUP BY p.id: они зависят от первичного ключа
        $statement = $this->pdo->prepare(
            'SELECT ' . self::CARD_COLUMNS . ' FROM category_post own
             JOIN LATERAL (
                 SELECT cp.post_id FROM category_post cp
                 WHERE cp.category_id = own.category_id AND cp.post_id <> own.post_id
                 ORDER BY cp.category_id DESC, cp.published_at DESC, cp.post_id DESC
                 LIMIT ' . self::SIMILAR_CANDIDATES . '
             ) other ON TRUE
             JOIN posts p ON p.id = other.post_id
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
