<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Env
{
    public static function get(string $name, ?string $default = null): string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default ?? throw new RuntimeException("Не задана переменная окружения {$name}");
        }

        return $value;
    }
}
