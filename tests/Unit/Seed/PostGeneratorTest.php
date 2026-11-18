<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seed;

use App\Blog\Slug;
use App\Seed\PostGenerator;
use App\Seed\Seeder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @phpstan-import-type SeedPost from Seeder
 */
final class PostGeneratorTest extends TestCase
{
    private const CATEGORIES = ['tools', 'agents', 'money', 'people'];

    public function testGeneratesRequestedNumberOfPostsWithUniqueSlugs(): void
    {
        $samples = array_column($this->samples(), null, 'title');
        $posts = $this->generator(250)->posts(0, 250);

        self::assertCount(250, $posts);
        $slugs = array_column($posts, 'slug');
        self::assertSame($slugs, array_unique($slugs));
        foreach ($posts as $i => $post) {
            self::assertSame($samples[$post['title']]['slug'] . '-' . ($i + 1), $post['slug']);
            self::assertTrue(Slug::isValid($post['slug']), $post['slug']);
        }
    }

    public function testSameInputGivesSamePostsAndBatchesMatchWholeRun(): void
    {
        $whole = $this->generator(10)->posts(0, 10);

        self::assertSame($whole, $this->generator(10)->posts(0, 10));
        self::assertSame($whole, [...$this->generator(10)->posts(0, 4), ...$this->generator(10)->posts(4, 6)]);
    }

    public function testPostKeepsMainCategoryOfSampleAndGetsUpToThreeKnownCategories(): void
    {
        $samples = array_column($this->samples(), null, 'title');

        $counts = [];
        foreach ($this->generator(300)->posts(0, 300) as $post) {
            self::assertSame($samples[$post['title']]['categories'][0], $post['categories'][0]);
            self::assertSame($post['categories'], array_values(array_unique($post['categories'])));
            self::assertSame([], array_diff($post['categories'], self::CATEGORIES));
            $counts[count($post['categories'])] = true;
        }

        ksort($counts);
        self::assertSame([1, 2, 3], array_keys($counts));
    }

    public function testCoverTextAndViewsComeFromSamples(): void
    {
        $samples = array_column($this->samples(), null, 'title');

        foreach ($this->generator(100)->posts(0, 100) as $post) {
            $sample = $samples[$post['title']];
            self::assertSame($sample['description'], $post['description']);
            self::assertSame($sample['image'] ?? null, $post['image'] ?? null);
            self::assertStringStartsWith("Вступление {$sample['title']}.\n\n", $post['content']);
            self::assertStringNotContainsString('## ', $post['content']);
            self::assertGreaterThanOrEqual(0, $post['views']);
        }
    }

    public function testDatesGrowOverTwoYearsBeforeOldestSample(): void
    {
        $dates = array_column($this->generator(1000)->posts(0, 1000), 'published_at');

        $sorted = $dates;
        sort($sorted);
        self::assertSame($sorted, $dates);
        // Самый старый образец - 2026-06-01 10:00:00, два года по 365 дней до него
        self::assertGreaterThanOrEqual('2024-06-01 10:00:00', $dates[0]);
        self::assertLessThan('2026-06-01 10:00:00', $dates[999]);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $dates[0]);
    }

    public function testNeedsSamples(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('демо-статья');

        new PostGenerator([], self::CATEGORIES, 10);
    }

    private function generator(int $total): PostGenerator
    {
        return new PostGenerator($this->samples(), self::CATEGORIES, $total);
    }

    /**
     * @return list<SeedPost>
     */
    private function samples(): array
    {
        $samples = [];
        foreach (['tool' => ['tools'], 'agent' => ['agents', 'tools'], 'money' => ['money']] as $slug => $categories) {
            $samples[] = [
                'slug' => $slug,
                'title' => "Статья {$slug}",
                'image' => "{$slug}.webp",
                'description' => "Описание {$slug}.",
                'categories' => $categories,
                'published_at' => '2026-06-01 10:00:00',
                'views' => 100,
                'content' => "Вступление Статья {$slug}.\n\n## Раздел\n\nАбзац {$slug}.\n\n- пункт",
            ];
        }
        // Образец без обложки: такие же статьи генерируются без неё
        $samples[] = [
            'slug' => 'bare',
            'title' => 'Статья bare',
            'description' => 'Описание bare.',
            'categories' => ['people'],
            'published_at' => '2026-07-01 10:00:00',
            'views' => 100,
            'content' => 'Вступление Статья bare.',
        ];

        return $samples;
    }
}
