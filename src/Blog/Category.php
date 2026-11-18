<?php

declare(strict_types=1);

namespace App\Blog;

use App\Database\Row;

final class Category
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
    ) {
    }

    public static function fromRow(Row $row): self
    {
        return new self($row->int('id'), $row->string('slug'), $row->string('name'), $row->string('description'));
    }
}
