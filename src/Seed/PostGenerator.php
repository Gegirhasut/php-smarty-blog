<?php

declare(strict_types=1);

namespace App\Seed;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Статьи для проверки на объёме: архив за два года до самой старой демо-статьи, даты растут с номером статьи.
 * Каждая статья повторяет случайный образец из демо-статей (заголовок с номером, описание, обложка, основная
 * категория) и получает ещё до двух категорий; текст - вступление образца и абзацы других статей.
 * Случайность - mt_rand() с постоянным seed: те же образцы дают те же статьи. Состояние mt_rand() общее
 * для процесса, поэтому статьи выдаются только по порядку, пачка за пачкой.
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
    private int $next = 0;

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
        mt_srand(self::SEED);
    }

    /**
     * Следующие $count статей.
     *
     * @return list<SeedPost>
     */
    public function posts(int $count): array
    {
        $posts = [];
        for ($last = $this->next + $count; $this->next < $last; $this->next++) {
            $number = $this->next + 1;
            $sample = $this->samples[mt_rand(0, count($this->samples) - 1)];

            $content = [explode("\n\n", $sample['content'], 2)[0]];
            for ($k = 0; $k < self::PARAGRAPHS && $this->paragraphs !== []; $k++) {
                $content[] = $this->paragraphs[mt_rand(0, count($this->paragraphs) - 1)];
            }

            $post = [
                // У slug образца в конце нет «-номер» другой статьи, поэтому номер делает slug уникальным,
                // а в заголовке - отличает в списках статьи одного образца
                'slug' => "{$sample['slug']}-{$number}",
                'title' => "{$sample['title']} (архив №{$number})",
                'description' => $sample['description'],
                'categories' => $this->categoriesFor($sample['categories'][0]),
                'published_at' => gmdate('Y-m-d H:i:s', $this->start + $this->next * $this->step + mt_rand(0, $this->step - 1)),
                'views' => mt_rand(0, self::MAX_VIEWS - 1),
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
    private function categoriesFor(string $main): array
    {
        $others = array_values(array_diff($this->categories, [$main]));
        // shuffle() берёт числа из того же mt_rand() и при том же seed тоже повторяется
        shuffle($others);

        return [$main, ...array_slice($others, 0, mt_rand(0, self::MAX_EXTRA_CATEGORIES))];
    }
}
