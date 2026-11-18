<?php

declare(strict_types=1);

namespace App\Core;

final class Meta
{
    /**
     * @param list<array<string, mixed>> $schema
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly string $description = '',
        public readonly ?string $canonical = null,
        public readonly string $ogType = 'website',
        public readonly ?string $image = null,
        public readonly array $schema = [],
    ) {
    }

    public function jsonLd(): string
    {
        // JSON_HEX_TAG экранирует < и >, чтобы текст из базы не смог закрыть тег script
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $this->schema],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR,
        );
    }
}
