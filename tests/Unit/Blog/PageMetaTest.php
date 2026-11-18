<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog;

use App\Blog\PageMeta;
use PHPUnit\Framework\TestCase;

final class PageMetaTest extends TestCase
{
    private PageMeta $meta;

    protected function setUp(): void
    {
        $this->meta = new PageMeta('Агентура', 'https://blog.test');
    }

    public function testHome(): void
    {
        $meta = $this->meta->home();

        self::assertSame('/', $meta->canonical);
        self::assertSame('/og.png', $meta->image);
        self::assertSame([[
            '@type' => 'WebSite',
            'name' => 'Агентура',
            'url' => 'https://blog.test/',
            'description' => $meta->description,
            'inLanguage' => 'ru',
        ]], $meta->schema);
    }
}
