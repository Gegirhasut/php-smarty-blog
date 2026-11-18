<?php

declare(strict_types=1);

namespace App\Blog;

use App\Core\NotFoundException;

/**
 * Сортировка и номер страницы из адреса категории: ?sort=views&page=2.
 */
final class ListingQuery
{
    public function __construct(
        public readonly Sort $sort = Sort::Date,
        public readonly int $page = 1,
    ) {
    }

    /**
     * Чего нет в адресе, берётся по умолчанию. Некорректное значение - это 404, а не первая страница:
     * иначе у каждого мусорного адреса был бы дубль, который поисковик мог бы проиндексировать.
     *
     * @param array<mixed> $query
     */
    public static function parse(array $query): self
    {
        $sort = $query['sort'] ?? Sort::Date->value;
        $page = $query['page'] ?? '1';

        // Номер только в обычной записи: «02», «+2» и «2.0» были бы дублями второй страницы.
        // Девяти цифр с запасом хватает, а LIMIT не переполнится
        if (!is_string($sort) || !is_string($page) || preg_match('/^[1-9][0-9]{0,8}$/', $page) !== 1) {
            throw new NotFoundException();
        }

        return new self(Sort::tryFrom($sort) ?? throw new NotFoundException(), (int) $page);
    }

    /**
     * Значения по умолчанию в адресе дублируют страницу без них: ?sort=date и ?page=1 нужно перевести
     * на адрес без этих параметров.
     *
     * @param array<mixed> $query запрос, из которого получен этот объект
     */
    public function isCanonical(array $query): bool
    {
        return !(isset($query['sort']) && $this->sort === Sort::Date)
            && !(isset($query['page']) && $this->page === 1);
    }

    public function withPage(int $page): self
    {
        return new self($this->sort, $page);
    }

    /**
     * Адрес страницы без значений по умолчанию, параметры всегда в одном порядке.
     */
    public function url(string $path): string
    {
        $params = array_filter([
            'sort' => $this->sort === Sort::Date ? null : $this->sort->value,
            'page' => $this->page === 1 ? null : $this->page,
        ]);

        return $params === [] ? $path : $path . '?' . http_build_query($params);
    }
}
