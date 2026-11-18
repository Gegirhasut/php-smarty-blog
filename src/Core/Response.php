<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $body,
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    /**
     * Постоянный редирект: поисковики переносят адрес в индексе на новый.
     */
    public static function redirect(string $url): self
    {
        return new self('', 301, ['Location' => $url]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers + ['Content-Type' => 'text/html; charset=utf-8'] as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}
