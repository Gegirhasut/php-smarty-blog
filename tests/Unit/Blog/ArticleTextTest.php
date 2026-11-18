<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog;

use App\Blog\ArticleText;
use PHPUnit\Framework\TestCase;

final class ArticleTextTest extends TestCase
{
    public function testSplitsTextIntoBlocks(): void
    {
        $text = <<<'TEXT'
            Вступление.

            ## Первый раздел

            Текст раздела.

            - первый пункт
            - второй пункт

            Вывод.
            TEXT;

        self::assertSame([
            ['type' => 'paragraph', 'text' => 'Вступление.'],
            ['type' => 'heading', 'text' => 'Первый раздел'],
            ['type' => 'paragraph', 'text' => 'Текст раздела.'],
            ['type' => 'list', 'items' => ['первый пункт', 'второй пункт']],
            ['type' => 'paragraph', 'text' => 'Вывод.'],
        ], (new ArticleText($text))->blocks());
    }

    public function testJoinsLinesOfParagraph(): void
    {
        self::assertSame(
            [['type' => 'paragraph', 'text' => 'Первая строка вторая строка.']],
            (new ArticleText("  Первая строка  \r\nвторая строка.\n"))->blocks(),
        );
    }

    public function testBlocksDoNotNeedEmptyLinesBetween(): void
    {
        $text = "Абзац перед списком:\n- пункт\nАбзац после списка.\n## Подзаголовок\nТекст.";

        self::assertSame([
            ['type' => 'paragraph', 'text' => 'Абзац перед списком:'],
            ['type' => 'list', 'items' => ['пункт']],
            ['type' => 'paragraph', 'text' => 'Абзац после списка.'],
            ['type' => 'heading', 'text' => 'Подзаголовок'],
            ['type' => 'paragraph', 'text' => 'Текст.'],
        ], (new ArticleText($text))->blocks());
    }

    public function testEmptyLineSeparatesTwoLists(): void
    {
        self::assertSame([
            ['type' => 'list', 'items' => ['а']],
            ['type' => 'list', 'items' => ['б']],
        ], (new ArticleText("- а\n\n- б"))->blocks());
    }

    public function testKeepsMarkupAsPlainText(): void
    {
        // Экранирует шаблон, а разбор не должен ни терять, ни менять такие символы
        $text = "<script>alert(1)</script>\n\n## A & B\n\n- <b>пункт</b>\n\n#не заголовок\n\n-не пункт";

        self::assertSame([
            ['type' => 'paragraph', 'text' => '<script>alert(1)</script>'],
            ['type' => 'heading', 'text' => 'A & B'],
            ['type' => 'list', 'items' => ['<b>пункт</b>']],
            ['type' => 'paragraph', 'text' => '#не заголовок'],
            ['type' => 'paragraph', 'text' => '-не пункт'],
        ], (new ArticleText($text))->blocks());
    }

    public function testEmptyTextHasNoBlocks(): void
    {
        self::assertSame([], (new ArticleText("\n\n  \n"))->blocks());
    }

    public function testReadingTime(): void
    {
        self::assertSame(1, (new ArticleText('Пара слов.'))->readingMinutes());
        self::assertSame(2, (new ArticleText(str_repeat('слово ', 400)))->readingMinutes());
        // Разметка и знаки препинания словами не считаются
        self::assertSame(3, (new ArticleText(str_repeat("## слово, - слово.\n", 300)))->readingMinutes());
    }
}
