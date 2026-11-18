<?php

declare(strict_types=1);

namespace App\Blog;

/**
 * Порядок статей в категории; значение - параметр ?sort= в адресе.
 */
enum Sort: string
{
    case Date = 'date';
    case Views = 'views';

    public function label(): string
    {
        return match ($this) {
            self::Date => 'Новые',
            self::Views => 'Популярные',
        };
    }
}
