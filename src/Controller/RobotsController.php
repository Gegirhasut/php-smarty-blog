<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;

/**
 * robots.txt собирается приложением, потому что адрес sitemap зависит от APP_URL.
 */
final class RobotsController
{
    public function __construct(private readonly string $siteUrl)
    {
    }

    public function index(): Response
    {
        // Индексировать можно всё: списки по просмотрам закрыты noindex в самих страницах,
        // а запрет в robots.txt не дал бы поисковику увидеть этот noindex
        $lines = [
            'User-agent: *',
            'Disallow:',
            '',
            "Sitemap: {$this->siteUrl}/sitemap.xml",
        ];

        return new Response(implode("\n", $lines) . "\n", headers: ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
