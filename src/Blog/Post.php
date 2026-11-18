<?php

declare(strict_types=1);

namespace App\Blog;

use App\Database\Row;
use DateTimeImmutable;

/**
 * Статья целиком, для её страницы. В списках статьи - PostCard, без текста.
 */
final class Post
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $description,
        public readonly string $image,
        public readonly int $views,
        public readonly DateTimeImmutable $publishedAt,
        public readonly string $content,
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
            content: $row->string('content'),
        );
    }

    public function withViews(int $views): self
    {
        return new self(
            $this->id,
            $this->slug,
            $this->title,
            $this->description,
            $this->image,
            $views,
            $this->publishedAt,
            $this->content,
        );
    }
}
