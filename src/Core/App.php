<?php

declare(strict_types=1);

namespace App\Core;

use App\Controller\HomeController;
use App\Repository\CategoryRepository;
use Closure;
use PDO;
use Throwable;

final class App
{
    private readonly View $view;
    private readonly Router $router;
    private ?PDO $pdo = null;

    /**
     * @param Closure(): PDO $connect открывает соединение с БД; вызывается при первом обращении к репозиторию
     */
    public function __construct(
        private readonly Config $config,
        string $rootDir,
        private readonly Closure $connect,
    ) {
        $this->view = new View(
            templateDir: $rootDir . '/templates',
            compileDir: $rootDir . '/var/templates_c',
            debug: $config->debug,
        );
        $this->view->share('site', ['name' => $config->siteName, 'url' => $config->siteUrl]);

        // Контроллер и его репозитории создаются только для совпавшего маршрута,
        // поэтому на 404 и 405 соединение с БД не открывается
        $this->router = new Router();
        $this->router->add('/', fn (): Response => (new HomeController($this->view, $this->categories()))->index());
    }

    public function handle(Request $request): Response
    {
        // HEAD обрабатываем как GET, тело ответа nginx отбросит сам
        if ($request->method !== 'GET' && $request->method !== 'HEAD') {
            return new Response('', 405, ['Allow' => 'GET, HEAD']);
        }

        try {
            return $this->dispatch($request);
        } catch (Throwable $e) {
            error_log((string) $e);
            $html = $this->view->render('errors/500.tpl', [
                'error' => $this->config->debug ? (string) $e : null,
            ]);

            return new Response($html, 500);
        }
    }

    private function dispatch(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (NotFoundException) {
            $html = $this->view->render('errors/404.tpl', [
                'meta' => new Meta(title: 'Страница не найдена'),
            ]);

            return new Response($html, 404);
        }
    }

    private function categories(): CategoryRepository
    {
        return new CategoryRepository($this->pdo());
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= ($this->connect)();
    }
}
