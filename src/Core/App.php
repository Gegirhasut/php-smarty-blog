<?php

declare(strict_types=1);

namespace App\Core;

use App\Controller\HomeController;
use Throwable;

final class App
{
    private readonly View $view;
    private readonly Router $router;

    public function __construct(private readonly Config $config, string $rootDir)
    {
        $this->view = new View(
            templateDir: $rootDir . '/templates',
            compileDir: $rootDir . '/var/templates_c',
            debug: $config->debug,
        );
        $this->view->share('site', ['name' => $config->siteName, 'url' => $config->siteUrl]);

        // Контроллер создаётся только для совпавшего маршрута, поэтому на 404 и 405
        // его зависимости, вроде соединения с БД, не поднимаются
        $this->router = new Router();
        $this->router->add('/', fn (): Response => (new HomeController($this->view))->index());
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
}
