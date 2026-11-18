<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * public/site.webmanifest - статичный файл, его никто не собирает: проверяем, что он разбирается,
 * а иконки на месте и того размера, что в нём записан.
 */
final class WebManifestTest extends TestCase
{
    public function testIconsExistWithDeclaredSizes(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $manifest = json_decode((string) file_get_contents($public . '/site.webmanifest'), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($manifest);
        self::assertSame('Агентура', $manifest['name']);
        self::assertIsArray($manifest['icons']);
        self::assertNotEmpty($manifest['icons']);
        foreach ($manifest['icons'] as $icon) {
            self::assertIsArray($icon);
            self::assertIsString($icon['src']);
            $size = getimagesize($public . $icon['src']);
            self::assertNotFalse($size, $icon['src']);
            self::assertSame($icon['sizes'], $size[0] . 'x' . $size[1]);
        }
    }
}
