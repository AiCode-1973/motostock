<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Rotas com opções:
 *  - area: main | panel | platform | store
 *  - perm: permissão exigida no painel (ver Permissions)
 *  - csrf: false para webhooks
 *  - guest: true para telas só de visitante (login, cadastro)
 *  - inactive: true para telas acessíveis com tenant suspenso/cancelado
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, array $handler, array $opts = []): void
    {
        $this->add('GET', $pattern, $handler, $opts);
    }

    public function post(string $pattern, array $handler, array $opts = []): void
    {
        $this->add('POST', $pattern, $handler, $opts);
    }

    public function add(string $method, string $pattern, array $handler, array $opts = []): void
    {
        $regex = preg_replace_callback('/\{([a-zA-Z_]+)\}/', static function (array $m): string {
            $name = $m[1];
            $re = match (true) {
                $name === 'id' || str_ends_with($name, 'Id') => '\d+',
                $name === 'uuid' => '[0-9a-f-]{36}',
                $name === 'slug' => '[a-z0-9-]+',
                $name === 'token' => '[A-Za-z0-9_-]{16,128}',
                default => '[^/]+',
            };
            return '(?P<' . $name . '>' . $re . ')';
        }, rtrim($pattern, '/') ?: '/');
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'opts' => $opts + ['area' => 'main'],
        ];
    }

    /** @return array{0: array, 1: array}|null|false  null = 404, false = método não permitido */
    public function match(string $method, string $path): array|null|false
    {
        $path = rtrim($path, '/') ?: '/';
        $methodMismatch = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                $methodMismatch = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return [$route, $params];
        }
        return $methodMismatch ? false : null;
    }
}
