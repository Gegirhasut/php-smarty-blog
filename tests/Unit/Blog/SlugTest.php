<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog;

use App\Blog\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function slugs(): array
    {
        return [
            'слово' => ['tools', true],
            'через дефис с цифрами' => ['gpt-6-1-terra', true],
            'начинается с цифр' => ['2026-itogi', true],
            'одна цифра' => ['7', false],
            'одни цифры' => ['2026', false],
            'кириллица' => ['привет', false],
            'верхний регистр' => ['Tools', false],
            'подчёркивание' => ['ii_agenty', false],
            'пробел' => ['ii agenty', false],
            'дефис в начале' => ['-tools', false],
            'дефис в конце' => ['tools-', false],
            'два дефиса подряд' => ['ii--agenty', false],
            'слеш' => ['a/b', false],
            'пустая строка' => ['', false],
            'перевод строки в конце' => ["tools\n", false],
        ];
    }

    #[DataProvider('slugs')]
    public function testChecksFormat(string $slug, bool $valid): void
    {
        self::assertSame($valid, Slug::isValid($slug));
    }
}
