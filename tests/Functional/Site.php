<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Core\App;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Tests\TempDir;
use Closure;
use PDO;

/**
 * Приложение целиком, без HTTP: запрос собирается из адреса так же, как его разобрал бы PHP.
 */
final class Site
{
    private readonly string $previousErrorLog;

    /**
     * @param TempDir $root корень сайта из root()
     * @param Closure(): PDO $connect
     */
    public function __construct(
        private readonly TempDir $root,
        private readonly Closure $connect,
        private readonly bool $debug = false,
    ) {
        @unlink($this->errorLogFile());
        $this->previousErrorLog = (string) ini_set('error_log', $this->errorLogFile());
    }

    /**
     * Копия шаблонов во временной папке: скомпилированные шаблоны не смешиваются с запущенным сайтом,
     * а тест может испортить шаблон, не трогая настоящий.
     */
    public static function root(): TempDir
    {
        $root = new TempDir();
        $root->copy(dirname(__DIR__, 2) . '/templates', 'templates');

        return $root;
    }

    public function get(string $url): Response
    {
        return $this->request('GET', $url);
    }

    public function request(string $method, string $url): Response
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $config = new Config(siteName: 'Агентура', siteUrl: 'http://blog.test', debug: $this->debug);

        return (new App($config, $this->root->path, $this->connect))
            ->handle(new Request($method, (string) parse_url($url, PHP_URL_PATH), $query));
    }

    public function errorLog(): string
    {
        return (string) @file_get_contents($this->errorLogFile());
    }

    public function close(): void
    {
        ini_set('error_log', $this->previousErrorLog);
    }

    private function errorLogFile(): string
    {
        return $this->root->path . '/error.log';
    }
}
