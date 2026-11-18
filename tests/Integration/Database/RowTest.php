<?php

declare(strict_types=1);

namespace App\Tests\Integration\Database;

use App\Database\Row;
use App\Tests\DatabaseTestCase;
use UnexpectedValueException;

final class RowTest extends DatabaseTestCase
{
    public function testReadsTypedValues(): void
    {
        $row = Row::first($this->select("SELECT 42 AS answer, 'текст' AS word, CAST('2026-10-09 16:33:00' AS DATETIME) AS published_at"));

        self::assertNotNull($row);
        self::assertSame(42, $row->int('answer'));
        self::assertSame('текст', $row->string('word'));
        // Время в базе - UTC, и дата не зависит от date.timezone
        self::assertSame('2026-10-09T16:33:00+00:00', $row->date('published_at')->format(DATE_ATOM));
    }

    public function testReadsNullableString(): void
    {
        $row = Row::first($this->select("SELECT NULL AS image, 'cover.webp' AS cover, 1 AS n"));
        self::assertNotNull($row);

        self::assertNull($row->nullableString('image'));
        self::assertSame('cover.webp', $row->nullableString('cover'));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Колонка n: ожидался string или null, получен int');

        $row->nullableString('n');
    }

    public function testReadsAllRows(): void
    {
        $rows = Row::all($this->select('SELECT 1 AS n UNION ALL SELECT 2'));

        self::assertSame([1, 2], array_map(fn (Row $row): int => $row->int('n'), $rows));
        self::assertSame([], Row::all($this->select('SELECT 1 AS n FROM DUAL WHERE FALSE')));
        self::assertNull(Row::first($this->select('SELECT 1 AS n FROM DUAL WHERE FALSE')));
    }

    public function testRejectsWrongType(): void
    {
        $row = Row::first($this->select("SELECT '42' AS answer"));
        self::assertNotNull($row);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Колонка answer: ожидался int, получен string');

        $row->int('answer');
    }

    public function testRejectsMissingColumn(): void
    {
        $row = Row::first($this->select('SELECT 1 AS n'));
        self::assertNotNull($row);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('нет колонки views');

        $row->int('views');
    }
}
