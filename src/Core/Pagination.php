<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

final class Pagination
{
    // Сколько номеров показывать по обе стороны от текущей страницы
    private const AROUND = 2;

    public readonly int $pages;

    public function __construct(
        public readonly int $page,
        int $total,
        public readonly int $perPage,
    ) {
        // У пустого списка тоже есть первая страница: на ней написано, что статей пока нет
        $this->pages = max(1, (int) ceil($total / $perPage));
    }

    public function exists(): bool
    {
        return $this->page >= 1 && $this->page <= $this->pages;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Ссылки для шаблона: соседние страницы и номера. Первая и последняя видны всегда,
     * пропуск отмечен null («…»), а пропуск из одной страницы короче показать номером.
     *
     * @param Closure(int): string $url адрес страницы по номеру
     * @return array{prev: ?string, next: ?string, pages: list<array{number: int, url: string, current: bool}|null>}
     */
    public function links(Closure $url): array
    {
        $pages = [];
        $previous = 0;
        foreach ($this->visibleNumbers() as $number) {
            if ($number - $previous === 2) {
                $pages[] = $this->link($number - 1, $url);
            } elseif ($number - $previous > 2) {
                $pages[] = null;
            }
            $pages[] = $this->link($number, $url);
            $previous = $number;
        }

        return [
            'prev' => $this->page > 1 ? $url($this->page - 1) : null,
            'next' => $this->page < $this->pages ? $url($this->page + 1) : null,
            'pages' => $pages,
        ];
    }

    /**
     * Первая, последняя и соседние с текущей, по возрастанию и без повторов. Номера берутся сразу, без прохода
     * по всем страницам: у большой категории их тысячи.
     *
     * @return list<int>
     */
    private function visibleNumbers(): array
    {
        $around = range(max(1, $this->page - self::AROUND), min($this->pages, $this->page + self::AROUND));

        return array_values(array_unique([1, ...$around, $this->pages]));
    }

    /**
     * @param Closure(int): string $url
     * @return array{number: int, url: string, current: bool}
     */
    private function link(int $number, Closure $url): array
    {
        return ['number' => $number, 'url' => $url($number), 'current' => $number === $this->page];
    }
}
