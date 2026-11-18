<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /**
     * @param array<mixed> $query
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        $uri = $_SERVER['REQUEST_URI'] ?? null;

        return new self(
            method: is_string($method) ? $method : 'GET',
            path: is_string($uri) ? explode('?', $uri, 2)[0] : '/',
            query: $_GET,
        );
    }
}
