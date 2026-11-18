<?php

declare(strict_types=1);

namespace App\Seo;

use App\Blog\Category;
use App\Blog\PostCard;
use App\Blog\Urls;
use App\Core\Config;

/**
 * llms.txt по формату llmstxt.org: название, описание цитатой и разделы со ссылками,
 * после двоеточия - краткое описание страницы. Это Markdown, поэтому HTML-экранирование здесь не нужно,
 * но квадратные скобки в тексте ссылки экранируются, чтобы не сломать её.
 */
final class LlmsTxt
{
    public function __construct(
        private readonly Config $config,
        private readonly Urls $urls,
    ) {
    }

    /**
     * Каждая статья попадает в раздел своей основной категории, без повторов.
     *
     * @param list<Category> $categories
     * @param array<int, list<PostCard>> $posts id категории => статьи, для которых она основная
     */
    public function render(array $categories, array $posts): string
    {
        $lines = ["# {$this->config->siteName}", '', '> ' . $this->inline($this->config->siteDescription), ''];

        foreach ($categories as $category) {
            $lines[] = '## ' . $this->inline($category->name);
            $lines[] = '';
            $lines[] = $this->link(
                "{$category->name}: все статьи",
                $this->urls->category($category->slug),
                $category->description,
            );
            foreach ($posts[$category->id] ?? [] as $post) {
                $lines[] = $this->link($post->title, $this->urls->post($post->slug), $post->description);
            }
            $lines[] = '';
        }

        // Раздел Optional по формату можно пропустить, если нужен короткий контекст
        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = $this->link('RSS', $this->urls->feed(), 'последние статьи блога');

        return implode("\n", $lines) . "\n";
    }

    private function link(string $title, string $path, string $description): string
    {
        $title = str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $this->inline($title));

        return "- [{$title}]({$this->urls->absolute($path)}): " . $this->inline($description);
    }

    /**
     * Перевод строки внутри пункта списка разорвал бы его, поэтому весь текст - в одну строку.
     */
    private function inline(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
