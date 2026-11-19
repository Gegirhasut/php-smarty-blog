-- Схема блога для MySQL 8.4. Применяется целиком из bin/seed.php, повторный запуск ничего не ломает.
-- Изменения схемы не мигрируются: данные учебные, базу пересоздаёт bin/seed.php --fresh.
-- Внутри комментариев нет точки с запятой: Schema делит файл на запросы по ней.

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    -- Slug - часть адреса: только латиница и точное сравнение, чтобы /category/News не открывал /category/news
    slug VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    description VARCHAR(500) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY categories_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    -- Обложка необязательна: без неё карточка показывает заглушку категории, а соцсети - картинку сайта
    image VARCHAR(255) NULL,
    description VARCHAR(500) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    published_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY posts_slug_unique (slug),
    -- Свежие статьи всего блога (RSS, добор похожих). id в конце ORDER BY делает порядок стабильным, а InnoDB
    -- неявно дописывает первичный ключ в конец вторичного индекса, поэтому ORDER BY published_at DESC, id DESC
    -- обслуживается этим индексом без filesort
    KEY posts_published_at_index (published_at),
    -- Узкий покрывающий индекс для sitemap: файл sitemap - диапазон id, а проход по первичному ключу читал бы
    -- строки целиком, вместе с текстом статьи
    KEY posts_sitemap_index (id, published_at, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS category_post (
    category_id INT UNSIGNED NOT NULL,
    post_id INT UNSIGNED NOT NULL,
    -- Порядок категорий статьи, как в её файле сидера. Первая по порядку - основная: она в хлебных крошках,
    -- первой среди меток и выбирает заглушку для статьи без обложки
    position TINYINT UNSIGNED NOT NULL,
    -- Копии posts.published_at и posts.views: по ним индексы ниже отдают статьи категории по порядку, не читая posts
    -- и не проходя статьи других категорий. Пишутся вместе со связью, просмотры увеличивает тот же UPDATE, что и в posts
    published_at DATETIME NOT NULL,
    views INT UNSIGNED NOT NULL,
    -- Первичный ключ не даёт связать статью с категорией дважды. UNIQUE отвечает на «категории статьи» по порядку
    -- (метки, похожие статьи), нужен внешнему ключу на posts и не даёт статье двух основных категорий
    PRIMARY KEY (category_id, post_id),
    UNIQUE KEY category_post_post_position_unique (post_id, position),
    -- Лента категории «сначала новые» и «сначала популярные», post_id в конце - для стабильного порядка.
    -- Индексы покрывающие: страница категории, главная и похожие берут из них id статей и читают posts только для них
    KEY category_post_category_published_index (category_id, published_at, post_id),
    KEY category_post_category_views_index (category_id, views, post_id),
    CONSTRAINT category_post_category_fk FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT category_post_post_fk FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
