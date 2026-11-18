<?php

declare(strict_types=1);

namespace App\Blog;

use App\Database\Row;
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
        public readonly string $image,
        public readonly int $views,
        public readonly DateTimeImmutable $publishedAt,
    ) {
    }

    public static function fromRow(Row $row): self
    {
        return new self(
            id: $row->int('id'),
            slug: $row->string('slug'),
            title: $row->string('title'),
            description: $row->string('description'),
            image: $row->string('image'),
            views: $row->int('views'),
            publishedAt: $row->date('published_at'),
        );
    }
}
