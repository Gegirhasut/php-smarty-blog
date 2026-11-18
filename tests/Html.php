<?php

declare(strict_types=1);

namespace App\Tests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Assert;

/**
 * Страница для проверок через XPath.
 */
final class Html
{
    private readonly DOMXPath $xpath;

    public function __construct(string $html)
    {
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR);
        $this->xpath = new DOMXPath($document);
    }

    public function count(string $query): int
    {
        return count($this->elements($query));
    }

    public function text(string $query): string
    {
        return $this->element($query)->textContent;
    }

    /**
     * @return list<string>
     */
    public function texts(string $query): array
    {
        return array_map(fn (DOMElement $element): string => trim($element->textContent), $this->elements($query));
    }

    public function attribute(string $query, string $name): string
    {
        return $this->element($query)->getAttribute($name);
    }

    /**
     * @return list<string>
     */
    public function attributes(string $query, string $name): array
    {
        return array_map(fn (DOMElement $element): string => $element->getAttribute($name), $this->elements($query));
    }

    /**
     * Узлы JSON-LD со всех тегов script, по типу.
     *
     * @return array<string, array<mixed>>
     */
    public function schema(): array
    {
        $nodes = [];
        foreach ($this->elements('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
            Assert::assertIsArray($data);
            Assert::assertIsArray($data['@graph']);
            foreach ($data['@graph'] as $node) {
                Assert::assertIsArray($node);
                Assert::assertIsString($node['@type']);
                $nodes[$node['@type']] = $node;
            }
        }

        return $nodes;
    }

    private function element(string $query): DOMElement
    {
        $elements = $this->elements($query);
        Assert::assertNotEmpty($elements, "Не найдено: {$query}");

        return $elements[0];
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(string $query): array
    {
        $elements = [];
        foreach ($this->xpath->query($query) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
