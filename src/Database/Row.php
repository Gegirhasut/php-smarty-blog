<?php

declare(strict_types=1);

namespace App\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDOStatement;
use UnexpectedValueException;

/**
 * Строка результата запроса. PDO отдаёт значения как mixed, а здесь каждое читается с проверкой типа:
 * если запрос и код разойдутся, ошибка будет сразу и с именем колонки, а не где-то дальше в шаблоне.
 */
final class Row
{
    /**
     * @param array<mixed> $values
     */
    private function __construct(private readonly array $values)
    {
    }

    /**
     * @return list<self>
     */
    public static function all(PDOStatement $statement): array
    {
        $rows = [];
        foreach ($statement->fetchAll() as $values) {
            $rows[] = self::from($values);
        }

        return $rows;
    }

    public static function first(PDOStatement $statement): ?self
    {
        $values = $statement->fetch();

        return $values === false ? null : self::from($values);
    }

    public function int(string $column): int
    {
        $value = $this->value($column);

        return is_int($value) ? $value : throw $this->typeError($column, 'int', $value);
    }

    public function string(string $column): string
    {
        $value = $this->value($column);

        return is_string($value) ? $value : throw $this->typeError($column, 'string', $value);
    }

    public function nullableString(string $column): ?string
    {
        $value = $this->value($column);

        return $value === null || is_string($value) ? $value : throw $this->typeError($column, 'string или null', $value);
    }

    /**
     * DATETIME из базы, где время хранится в UTC.
     */
    public function date(string $column): DateTimeImmutable
    {
        return new DateTimeImmutable($this->string($column), new DateTimeZone('UTC'));
    }

    private static function from(mixed $values): self
    {
        return is_array($values) ? new self($values) : throw new UnexpectedValueException('Строка результата - не массив');
    }

    private function value(string $column): mixed
    {
        if (!array_key_exists($column, $this->values)) {
            throw new UnexpectedValueException("В строке результата нет колонки {$column}");
        }

        return $this->values[$column];
    }

    private function typeError(string $column, string $expected, mixed $value): UnexpectedValueException
    {
        return new UnexpectedValueException("Колонка {$column}: ожидался {$expected}, получен " . get_debug_type($value));
    }
}
