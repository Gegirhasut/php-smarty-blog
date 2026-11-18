<?php

declare(strict_types=1);

namespace App\Blog;

use DateTimeImmutable;

/**
 * Статья в списках: всё для карточки, но без текста.
 */
final class PostCard
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $description,
        // Путь к обложке; null - статья без обложки
        public readonly ?string $image,
        public readonly int $views,
        public readonly DateTimeImmutable $publishedAt,
    ) {
    }
}
