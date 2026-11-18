<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo;

use App\Seo\Meta;
use PHPUnit\Framework\TestCase;

final class MetaTest extends TestCase
{
    public function testJsonLdWrapsSchemaIntoGraph(): void
    {
        $schema = [['@type' => 'WebSite', 'name' => 'Блог', 'url' => 'https://example.com/']];

        $json = json_decode((new Meta(schema: $schema))->jsonLd(), true);

        self::assertSame(['@context' => 'https://schema.org', '@graph' => $schema], $json);
    }

    public function testJsonLdCannotCloseScriptTag(): void
    {
        $headline = '</script><script>alert(1)</script>';

        $jsonLd = (new Meta(schema: [['headline' => $headline]]))->jsonLd();

        self::assertStringNotContainsString('<', $jsonLd);
        self::assertStringNotContainsString('>', $jsonLd);
        self::assertSame(['headline' => $headline], $this->firstNode($jsonLd));
    }

    public function testJsonLdKeepsTextReadable(): void
    {
        $jsonLd = (new Meta(schema: [['name' => 'Путешествия', 'url' => 'https://example.com/category/travel']]))->jsonLd();

        self::assertStringContainsString('"Путешествия"', $jsonLd);
        self::assertStringContainsString('"https://example.com/category/travel"', $jsonLd);
    }

    /**
     * @return array<mixed>
     */
    private function firstNode(string $jsonLd): array
    {
        $data = json_decode($jsonLd, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['@graph']);
        self::assertIsArray($data['@graph'][0]);

        return $data['@graph'][0];
    }
}
