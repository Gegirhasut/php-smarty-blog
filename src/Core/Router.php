<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, callable(Request, array<string, string>): Response> */
    private array $routes = [];

    /**
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function add(string $pattern, callable $handler): void
    {
        $this->routes[$pattern] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $pattern => $handler) {
            $params = $this->match($pattern, $request->path);

            if ($params !== null) {
                return $handler($request, $params);
            }
        }

        throw new NotFoundException();
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $patternParts = explode('/', $pattern);
        $pathParts = explode('/', $path);

        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];
        foreach ($patternParts as $i => $part) {
            // Декодируем уже после разбиения, чтобы %2F внутри slug не стал разделителем
            $segment = rawurldecode($pathParts[$i]);

            if (preg_match('/^\{(\w+)\}$/', $part, $matches) === 1) {
                if ($segment === '') {
                    return null;
                }
                $params[$matches[1]] = $segment;
            } elseif ($part !== $segment) {
                return null;
            }
        }

        return $params;
    }
}
