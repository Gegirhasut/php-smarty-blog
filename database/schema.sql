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
    image VARCHAR(255) NOT NULL,
    description VARCHAR(500) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    published_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY posts_slug_unique (slug),
    -- Ленты «сначала новые» и «сначала популярные» сортируются с id в конце ORDER BY, это и делает порядок стабильным.
    -- InnoDB неявно дописывает первичный ключ в конец вторичного индекса, поэтому ORDER BY published_at DESC, id DESC
    -- (и так же по views) обслуживается индексом без filesort и без отдельного составного индекса
    KEY posts_published_at_index (published_at),
    KEY posts_views_index (views)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS category_post (
    category_id INT UNSIGNED NOT NULL,
    post_id INT UNSIGNED NOT NULL,
    -- Порядок категорий статьи, как в её файле сидера. Первая по порядку - основная: она в хлебных крошках,
    -- первой среди меток и в llms.txt
    position TINYINT UNSIGNED NOT NULL,
    -- Первичный ключ отвечает на «статьи категории», второй индекс - на «категории статьи» по порядку
    -- (метки, похожие статьи) и нужен внешнему ключу на posts. UNIQUE не даёт статье двух основных категорий
    PRIMARY KEY (category_id, post_id),
    UNIQUE KEY category_post_post_position_unique (post_id, position),
    CONSTRAINT category_post_category_fk FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT category_post_post_fk FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
