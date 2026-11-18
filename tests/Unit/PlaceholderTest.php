<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Core\View;
use App\Tests\Html;
use App\Tests\TempDir;
use PHPUnit\Framework\TestCase;

/**
 * Заглушки вместо обложек привязаны к slug категории в шаблоне: у каждой категории сидера должна быть своя,
 * а у любой другой - общая, и адрес всегда ведёт на существующий файл.
 */
final class PlaceholderTest extends TestCase
{
    private static TempDir $compiled;
    private static View $view;
    private static string $public;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);
        self::$public = $root . '/public';
        self::$compiled = new TempDir();
        self::$view = new View($root . '/templates', self::$compiled->path, self::$public, debug: true);
    }

    public static function tearDownAfterClass(): void
    {
        self::$compiled->remove();
    }

    public function testEverySeedCategoryHasItsOwnPlaceholder(): void
    {
        $categories = require dirname(__DIR__, 2) . '/database/seeds/categories.php';
        self::assertIsArray($categories);
        foreach ($categories as $category) {
            self::assertIsArray($category);
            self::assertIsString($category['slug']);

            self::assertSame("/assets/img/placeholders/{$category['slug']}.webp", $this->placeholder($category['slug']));
        }
    }

    public function testOtherCategoryGetsCommonPlaceholder(): void
    {
        self::assertSame('/assets/img/placeholders/ii-agenty.webp', $this->placeholder('food'));
        self::assertSame('/assets/img/placeholders/ii-agenty.webp', $this->placeholder(null));
    }

    /**
     * Адрес заглушки без версии; файл по нему должен быть.
     */
    private function placeholder(?string $slug): string
    {
        $html = new Html(self::$view->render(
            'string:{include "partials/placeholder.tpl" class="card__image" slug=$slug priority=false}',
            ['slug' => $slug],
        ));
        $src = $html->attribute('//img', 'src');
        $path = (string) strtok($src, '?');
        self::assertFileExists(self::$public . $path);
        self::assertStringContainsString('?v=', $src);

        return $path;
    }
}
