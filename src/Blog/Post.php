<?php

declare(strict_types=1);

namespace App\Blog;

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
        // Путь к обложке; null - статья без обложки
        public readonly ?string $image,
        public readonly int $views,
        public readonly DateTimeImmutable $publishedAt,
        public readonly string $content,
    ) {
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
