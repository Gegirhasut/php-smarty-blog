<?php

declare(strict_types=1);

namespace App\Seed;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Статьи для проверки на объёме: архив за два года до самой старой демо-статьи, даты растут с номером статьи.
 * Каждая статья повторяет случайный образец из демо-статей (заголовок, описание, обложка, основная категория)
 * и получает ещё до двух категорий; текст - вступление образца и абзацы других статей.
 * Случайность - хэш от seed, номера статьи и поля, а не генератор с состоянием: те же образцы дают те же статьи,
 * и любую пачку можно построить отдельно.
 *
 * @phpstan-import-type SeedPost from Seeder
 */
final class PostGenerator
{
    private const SEED = 2026;
    private const SPAN = 2 * 365 * 86400;
    private const MAX_EXTRA_CATEGORIES = 2;
    private const MAX_VIEWS = 20_000;
    private const PARAGRAPHS = 2;

    /** @var list<string> */
    private readonly array $paragraphs;
    private readonly int $start;
    private readonly int $step;

    /**
     * @param list<SeedPost> $samples
     * @param list<string> $categories slug всех категорий
     */
    public function __construct(
        private readonly array $samples,
        private readonly array $categories,
        int $total,
    ) {
        if ($samples === []) {
            throw new RuntimeException('Для генерации статей нужна хотя бы одна демо-статья - образец');
        }

        $paragraphs = [];
        foreach ($samples as $sample) {
            foreach (explode("\n\n", $sample['content']) as $block) {
                if (!str_starts_with($block, '## ') && !str_starts_with($block, '- ')) {
                    $paragraphs[] = $block;
                }
            }
        }
        $this->paragraphs = $paragraphs;

        $oldest = new DateTimeImmutable(min(array_column($samples, 'published_at')), new DateTimeZone('UTC'));
        $this->start = $oldest->getTimestamp() - self::SPAN;
        $this->step = max(1, intdiv(self::SPAN, max(1, $total)));
    }

    /**
     * Статьи с номерами от $from до $from + $count - 1.
     *
     * @return list<SeedPost>
     */
    public function posts(int $from, int $count): array
    {
        $posts = [];
        for ($i = $from; $i < $from + $count; $i++) {
            $sample = $this->samples[$this->number($i, 'sample', count($this->samples))];

            $content = [explode("\n\n", $sample['content'], 2)[0]];
            for ($k = 0; $k < self::PARAGRAPHS && $this->paragraphs !== []; $k++) {
                $content[] = $this->paragraphs[$this->number($i, "paragraph-{$k}", count($this->paragraphs))];
            }

            $post = [
                // У slug образца в конце нет «-номер» другой статьи, поэтому номер делает slug уникальным
                'slug' => "{$sample['slug']}-" . ($i + 1),
                'title' => $sample['title'],
                'description' => $sample['description'],
                'categories' => $this->categoriesFor($i, $sample['categories'][0]),
                'published_at' => gmdate('Y-m-d H:i:s', $this->start + $i * $this->step + $this->number($i, 'time', $this->step)),
                'views' => $this->number($i, 'views', self::MAX_VIEWS),
                'content' => implode("\n\n", $content),
            ];
            if (isset($sample['image'])) {
                $post['image'] = $sample['image'];
            }
            $posts[] = $post;
        }

        return $posts;
    }

    /**
     * @return list<string> основная категория образца и до двух других без повторов
     */
    private function categoriesFor(int $i, string $main): array
    {
        $categories = [$main];
        $others = array_values(array_diff($this->categories, $categories));
        $extra = min($this->number($i, 'extra', self::MAX_EXTRA_CATEGORIES + 1), count($others));
        for ($k = 0; $k < $extra; $k++) {
            $categories[] = array_splice($others, $this->number($i, "category-{$k}", count($others)), 1)[0];
        }

        return $categories;
    }

    /**
     * Число от 0 до $max - 1, которое зависит только от seed, номера статьи и поля.
     */
    private function number(int $i, string $field, int $max): int
    {
        return (int) hexdec(hash('xxh32', self::SEED . ":{$i}:{$field}")) % $max;
    }
}
