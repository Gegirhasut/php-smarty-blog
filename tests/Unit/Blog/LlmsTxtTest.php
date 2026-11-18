<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog;

use App\Blog\Category;
use App\Blog\LlmsTxt;
use App\Blog\PostCard;
use App\Core\Config;
use DateTimeImmutable;
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
            'category' => new Category(1, 'tools', 'Инструменты', 'Обзоры сервисов.'),
            'posts' => [$this->post('first', 'Первая', 'О чём статья.')],
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
            'category' => new Category(1, 'tools', 'Инструменты', "Первая строка\nвторая."),
            'posts' => [$this->post('first', 'Обзор [бета] \ релиз', 'Описание')],
        ]]);

        self::assertStringContainsString('): Первая строка вторая.', $text);
        self::assertStringContainsString('- [Обзор \[бета\] \\\\ релиз](https://blog.test/post/first): Описание', $text);
    }

    private function post(string $slug, string $title, string $description): PostCard
    {
        return new PostCard(7, $slug, $title, $description, "/uploads/{$slug}.webp", 0, new DateTimeImmutable('2026-10-01 10:00:00'));
    }
}
