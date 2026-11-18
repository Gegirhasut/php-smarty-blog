<?php

declare(strict_types=1);

namespace App\Blog;

/**
 * Slug - часть адреса категории или статьи: латиница в нижнем регистре и цифры, слова через дефис.
 * Сидер не пускает в базу другие, а репозитории не ищут их: колонка slug в кодировке ascii,
 * и сравнить её со строкой с кириллицей MySQL не может (ошибка 3988 вместо «не найдено»).
 */
final class Slug
{
    public static function isValid(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) === 1;
    }
}
