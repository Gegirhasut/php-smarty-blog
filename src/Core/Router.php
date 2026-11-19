<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, callable(Request, array<string, string>): Response> регулярное выражение => обработчик */
    private array $routes = [];

    /**
     * Параметр в шаблоне - {имя}: непустая часть одного сегмента пути (/post/{slug}, /sitemap-posts-{number}.xml).
     * Остальное сравнивается с адресом как есть, без декодирования: /sitemap%2Exml - не /sitemap.xml.
     *
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function add(string $pattern, callable $handler): void
    {
        $regex = '';
        foreach (preg_split('/(\{\w+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $regex .= preg_match('/^\{(\w+)\}$/', $part, $name) === 1 ? "(?<{$name[1]}>[^/]+)" : preg_quote($part, '#');
        }

        $this->routes["#^{$regex}$#D"] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $regex => $handler) {
            $params = $this->match($regex, $request->path);

            if ($params !== null) {
                return $handler($request, $params);
            }
        }

        // Адрес маршрута со слэшем на конце (/post/slug/) - не другая страница: 301 на адрес без слэша
        $path = rtrim($request->path, '/');
        if ($path !== '' && $path !== $request->path && $this->matchesAny($path)) {
            $query = http_build_query($request->query, '', '&', PHP_QUERY_RFC3986);

            return Response::redirect($query === '' ? $path : "{$path}?{$query}");
        }

        throw new NotFoundException();
    }

    private function matchesAny(string $path): bool
    {
        foreach (array_keys($this->routes) as $regex) {
            if ($this->match($regex, $path) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $regex, string $path): ?array
    {
        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $name => $raw) {
            if (!is_string($name)) {
                continue;
            }
            // Декодируем уже после разбиения, чтобы %2F внутри slug не стал разделителем. Закодированный без нужды
            // параметр (/category/den%67i вместо /category/dengi) - дубль страницы, такой адрес не совпадает
            $value = rawurldecode($raw);
            if (rawurlencode($value) !== $raw) {
                return null;
            }
            $params[$name] = $value;
        }

        return $params;
    }
}
