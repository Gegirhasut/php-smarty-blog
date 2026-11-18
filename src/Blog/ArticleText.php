<?php

declare(strict_types=1);

namespace App\Blog;

/**
 * Текст статьи в том виде, как он хранится в базе: абзацы через пустую строку, подзаголовки «## »
 * и пункты списка «- ». Здесь он только разбирается на блоки, HTML строит шаблон со своим экранированием,
 * поэтому разметка из текста не попадёт на страницу.
 *
 * @phpstan-type Block array{type: 'heading'|'paragraph', text: string}|array{type: 'list', items: list<string>}
 */
final class ArticleText
{
    // Средняя скорость чтения статьи, слов в минуту
    private const WORDS_PER_MINUTE = 200;

    public function __construct(private readonly string $text)
    {
    }

    /**
     * @return list<Block>
     */
    public function blocks(): array
    {
        /** @var list<Block> $blocks */
        $blocks = [];
        $paragraph = [];
        $items = [];

        // Пустая строка в конце закрывает последний блок
        foreach ([...preg_split('/\R/u', $this->text) ?: [], ''] as $line) {
            $line = trim($line);
            $isHeading = str_starts_with($line, '## ');
            $isItem = str_starts_with($line, '- ');

            // Абзац заканчивается пустой строкой, подзаголовком или началом списка, список - любой строкой кроме пункта
            if ($paragraph !== [] && ($line === '' || $isHeading || $isItem)) {
                $blocks[] = ['type' => 'paragraph', 'text' => implode(' ', $paragraph)];
                $paragraph = [];
            }
            if ($items !== [] && !$isItem) {
                $blocks[] = ['type' => 'list', 'items' => $items];
                $items = [];
            }

            if ($isHeading) {
                $blocks[] = ['type' => 'heading', 'text' => ltrim(substr($line, 3))];
            } elseif ($isItem) {
                $items[] = ltrim(substr($line, 2));
            } elseif ($line !== '') {
                $paragraph[] = $line;
            }
        }

        return $blocks;
    }

    public function readingMinutes(): int
    {
        $words = preg_match_all('/[\p{L}\p{N}]+/u', $this->text);

        return max(1, (int) round($words / self::WORDS_PER_MINUTE));
    }
}
