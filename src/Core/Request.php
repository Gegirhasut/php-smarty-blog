<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            path: explode('?', $uri, 2)[0],
            query: $_GET,
        );
    }
}
