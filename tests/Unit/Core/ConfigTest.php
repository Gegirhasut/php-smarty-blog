<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core;

use App\Core\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    private const VARIABLES = ['APP_ENV', 'APP_URL'];

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    public function testReadsEnvironment(): void
    {
        putenv('APP_ENV=dev');
        putenv('APP_URL=https://blog.example.com/');

        $config = Config::load();

        self::assertTrue($config->debug);
        self::assertSame('https://blog.example.com', $config->siteUrl);
        self::assertSame('Агентура', $config->siteName);
        self::assertNotSame('', $config->siteDescription);
    }

    public function testProductionModeByDefault(): void
    {
        putenv('APP_URL=https://blog.example.com');

        self::assertFalse(Config::load()->debug);
    }

    public function testRequiresSiteUrl(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_URL');

        Config::load();
    }
}
