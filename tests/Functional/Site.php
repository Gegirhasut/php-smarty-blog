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
    public readonly TempDir $root;
    private readonly string $previousErrorLog;

    /**
     * @param Closure(): PDO $connect
     */
    public function __construct(private readonly Closure $connect, private readonly bool $debug = false)
    {
        // Отдельный корень, чтобы тесты не делили скомпилированные шаблоны с запущенным сайтом
        $this->root = new TempDir();
        $this->root->copy(dirname(__DIR__, 2) . '/templates', 'templates');
        $this->previousErrorLog = (string) ini_set('error_log', $this->root->path . '/error.log');
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
        return (string) @file_get_contents($this->root->path . '/error.log');
    }

    public function remove(): void
    {
        ini_set('error_log', $this->previousErrorLog);
        $this->root->remove();
    }
}
