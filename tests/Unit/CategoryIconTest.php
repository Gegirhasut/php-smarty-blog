<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Core\View;
use App\Tests\TempDir;
use PHPUnit\Framework\TestCase;

/**
 * Иконки категорий привязаны в шаблоне к slug: если slug в сидере поменяется, категория молча получит общую иконку.
 */
final class CategoryIconTest extends TestCase
{
    private static TempDir $compiled;
    private static View $view;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);
        self::$compiled = new TempDir();
        self::$view = new View($root . '/templates', self::$compiled->path, $root . '/public', debug: true);
    }

    public static function tearDownAfterClass(): void
    {
        self::$compiled->remove();
    }

    public function testEverySeedCategoryHasItsOwnIcon(): void
    {
        $categories = require dirname(__DIR__, 2) . '/database/seeds/categories.php';
        self::assertIsArray($categories);
        $icons = [];
        foreach ($categories as $category) {
            self::assertIsArray($category);
            self::assertIsString($category['slug']);
            $icons[$category['slug']] = $this->icon($category['slug']);
        }

        self::assertNotContains($this->icon('unknown'), $icons);
        self::assertSame($icons, array_unique($icons));
    }

    public function testUnknownCategoryGetsCommonIcon(): void
    {
        self::assertStringStartsWith('<svg', $this->icon('unknown'));
        self::assertSame($this->icon('unknown'), $this->icon('other'));
    }

    private function icon(string $slug): string
    {
        return trim(self::$view->render('string:{include "partials/category-icon.tpl" slug=$slug}', ['slug' => $slug]));
    }
}
