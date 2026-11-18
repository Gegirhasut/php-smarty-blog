<?php

declare(strict_types=1);

namespace App\Core;

use App\Blog\Urls;
use App\Controller\CategoryController;
use App\Controller\FeedController;
use App\Controller\HomeController;
use App\Controller\LlmsController;
use App\Controller\PostController;
use App\Controller\RobotsController;
use App\Controller\SitemapController;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Seo\LlmsTxt;
use App\Seo\PageMeta;
use App\Seo\RssFeed;
use App\Seo\Sitemap;
use Closure;
use PDO;
use Throwable;

final class App
{
    private readonly View $view;
    private readonly Urls $urls;
    private readonly PageMeta $meta;
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
            publicDir: $rootDir . '/public',
            debug: $config->debug,
        );
        $this->urls = new Urls($config->siteUrl);
        $this->view->share('site', ['name' => $config->siteName, 'description' => $config->siteDescription]);
        $this->view->share('urls', $this->urls);
        $this->meta = new PageMeta($config, $this->urls);
        $this->router = $this->routes();
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
                'meta' => $this->meta->notFound(),
            ]);

            return new Response($html, 404);
        }
    }

    /**
     * Контроллер и его репозитории создаются только для совпавшего маршрута, а соединение с БД - при первом
     * обращении к репозиторию, поэтому 404, 405 и robots.txt обходятся без базы.
     */
    private function routes(): Router
    {
        $router = new Router();

        $router->add('/', fn (): Response => (new HomeController(
            $this->view,
            $this->meta,
            $this->categories(),
            $this->posts(),
        ))->index());
        $router->add('/category/{slug}', fn (Request $request, array $params): Response => (new CategoryController(
            $this->view,
            $this->meta,
            $this->urls,
            $this->categories(),
            $this->posts(),
        ))->show($request, $params['slug']));
        $router->add('/post/{slug}', fn (Request $request, array $params): Response => (new PostController(
            $this->view,
            $this->meta,
            $this->categories(),
            $this->posts(),
        ))->show($request, $params['slug']));
        $router->add('/sitemap.xml', fn (): Response => (new SitemapController(
            new Sitemap($this->urls),
            $this->categories(),
            $this->posts(),
        ))->index());
        $router->add('/feed.xml', fn (): Response => (new FeedController(
            new RssFeed($this->config, $this->urls),
            $this->categories(),
            $this->posts(),
        ))->index());
        $router->add('/llms.txt', fn (): Response => (new LlmsController(
            new LlmsTxt($this->config, $this->urls),
            $this->categories(),
            $this->posts(),
        ))->index());
        $router->add('/robots.txt', fn (): Response => (new RobotsController($this->urls))->index());

        return $router;
    }

    private function categories(): CategoryRepository
    {
        return new CategoryRepository($this->pdo());
    }

    private function posts(): PostRepository
    {
        return new PostRepository($this->pdo());
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= ($this->connect)();
    }
}
