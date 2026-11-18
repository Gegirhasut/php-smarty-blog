<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog;

use App\Blog\LlmsTxt;
use App\Core\Config;
use PHPUnit\Framework\TestCase;

final class LlmsTxtTest extends TestCase
{
    private LlmsTxt $llms;

    protected function setUp(): void
    {
        $this->llms = new LlmsTxt(new Config('Агентура', 'Блог об ИИ.', 'https://blog.test', debug: false));
    }

    public function testListsCategoriesWithTheirPosts(): void
    {
        $text = $this->llms->render([[
            'category' => ['id' => 1, 'slug' => 'tools', 'name' => 'Инструменты', 'description' => 'Обзоры сервисов.'],
            'posts' => [[
                'id' => 7,
                'slug' => 'first',
                'title' => 'Первая',
                'description' => 'О чём статья.',
                'image' => '/uploads/first.webp',
                'views' => 0,
                'published_at' => '2026-10-01 10:00:00',
            ]],
        ]]);

        self::assertSame(<<<'TEXT'
            # Агентура

            > Блог об ИИ.

            ## Инструменты

            - [Инструменты: все статьи](https://blog.test/category/tools): Обзоры сервисов.
            - [Первая](https://blog.test/post/first): О чём статья.

            ## Optional

            - [RSS](https://blog.test/feed.xml): последние статьи блога

            TEXT, $text);
    }

    public function testKeepsLinksIntact(): void
    {
        $text = $this->llms->render([[
            'category' => ['id' => 1, 'slug' => 'tools', 'name' => 'Инструменты', 'description' => "Первая строка\nвторая."],
            'posts' => [[
                'id' => 7,
                'slug' => 'first',
                'title' => 'Обзор [бета] \ релиз',
                'description' => 'Описание',
                'image' => '/uploads/first.webp',
                'views' => 0,
                'published_at' => '2026-10-01 10:00:00',
            ]],
        ]]);

        self::assertStringContainsString('): Первая строка вторая.', $text);
        self::assertStringContainsString('- [Обзор \[бета\] \\\\ релиз](https://blog.test/post/first): Описание', $text);
    }
}
