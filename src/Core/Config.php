<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Config
{
    public function __construct(
        public readonly string $siteName,
        public readonly string $siteUrl,
        public readonly bool $debug,
    ) {
    }

    public static function load(): self
    {
        return new self(
            siteName: 'Блог',
            siteUrl: rtrim(self::env('APP_URL'), '/'),
            debug: self::env('APP_ENV', 'prod') === 'dev',
        );
    }

    private static function env(string $name, ?string $default = null): string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default ?? throw new RuntimeException("Не задана переменная окружения {$name}");
        }

        return $value;
    }
}
