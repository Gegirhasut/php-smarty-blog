<?php

declare(strict_types=1);

namespace App\Blog;

use App\Core\Config;

/**
 * llms.txt по формату llmstxt.org: название, описание цитатой и разделы со ссылками,
 * после двоеточия - краткое описание страницы. Это Markdown, поэтому HTML-экранирование здесь не нужно,
 * но квадратные скобки в тексте ссылки экранируются, чтобы не сломать её.
 */
final class LlmsTxt
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param list<array{category: Category, posts: list<PostCard>}> $sections
     */
    public function render(array $sections): string
    {
        $lines = ["# {$this->config->siteName}", '', '> ' . $this->inline($this->config->siteDescription), ''];

        foreach ($sections as $section) {
            $category = $section['category'];
            $lines[] = '## ' . $this->inline($category->name);
            $lines[] = '';
            $lines[] = $this->link(
                "{$category->name}: все статьи",
                '/category/' . $category->slug,
                $category->description,
            );
            foreach ($section['posts'] as $post) {
                $lines[] = $this->link($post->title, '/post/' . $post->slug, $post->description);
            }
            $lines[] = '';
        }

        // Раздел Optional по формату можно пропустить, если нужен короткий контекст
        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = $this->link('RSS', '/feed.xml', 'последние статьи блога');

        return implode("\n", $lines) . "\n";
    }

    private function link(string $title, string $path, string $description): string
    {
        $title = str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $this->inline($title));

        return "- [{$title}]({$this->config->siteUrl}{$path}): " . $this->inline($description);
    }

    /**
     * Перевод строки внутри пункта списка разорвал бы его, поэтому весь текст - в одну строку.
     */
    private function inline(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
