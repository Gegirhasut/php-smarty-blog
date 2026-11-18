<?php

declare(strict_types=1);

namespace App\Blog;

/**
 * Сортировка и номер страницы из адреса категории: ?sort=views&page=2.
 */
final class ListingQuery
{
    public function __construct(
        public readonly Sort $sort = Sort::Date,
        public readonly int $page = 1,
        // Адрес без лишних параметров. ?sort=date и ?page=1 повторяют значения по умолчанию,
        // и такой адрес - дубль адреса без них
        public readonly bool $isCanonical = true,
    ) {
    }

    /**
     * @param array<mixed> $query параметры адреса
     * @return self|null null, если sort или page некорректны
     */
    public static function parse(array $query): ?self
    {
        $sortValue = $query['sort'] ?? Sort::Date->value;
        $pageValue = $query['page'] ?? '1';

        // Номер только в обычной записи: «02», «+2» и «2.0» были бы дублями второй страницы.
        // Девяти цифр с запасом хватает, а LIMIT не переполнится
        if (!is_string($sortValue) || !is_string($pageValue) || preg_match('/^[1-9][0-9]{0,8}$/D', $pageValue) !== 1) {
            return null;
        }
        $sort = Sort::tryFrom($sortValue);
        if ($sort === null) {
            return null;
        }
        $page = (int) $pageValue;

        return new self(
            $sort,
            $page,
            isCanonical: !(isset($query['sort']) && $sort === Sort::Date) && !(isset($query['page']) && $page === 1),
        );
    }

    public function withPage(int $page): self
    {
        return new self($this->sort, $page);
    }

    /**
     * Параметры для адреса: значения по умолчанию не пишутся, порядок всегда один.
     *
     * @return array<string, string|int>
     */
    public function params(): array
    {
        return array_filter([
            'sort' => $this->sort === Sort::Date ? null : $this->sort->value,
            'page' => $this->page === 1 ? null : $this->page,
        ]);
    }
}
