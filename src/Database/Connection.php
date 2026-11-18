<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\Env;
use PDO;

final class Connection
{
    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    public static function fromEnvironment(?string $database = null): self
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST'),
            Env::get('DB_PORT', '3306'),
            $database ?? Env::get('DB_NAME'),
        );

        return new self($dsn, Env::get('DB_USER'), Env::get('DB_PASSWORD', ''));
    }

    public function connect(): PDO
    {
        return new PDO($this->dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
