<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    public function __construct(
        public readonly string $siteName,
        public readonly string $siteDescription,
        public readonly string $siteUrl,
        public readonly bool $debug,
    ) {
    }

    public static function load(): self
    {
        return new self(
            siteName: 'Агентура',
            siteDescription: 'Блог об ИИ: новые модели и инструменты, агенты, исследования, деньги и люди.',
            siteUrl: rtrim(Env::get('APP_URL'), '/'),
            debug: Env::get('APP_ENV', 'prod') === 'dev',
        );
    }
}
