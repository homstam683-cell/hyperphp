<?php

declare(strict_types=1);

namespace HyperPHP\Core;

/**
 * Router
 *
 * v0.1 : routage exact + wildcards simples, suffisant pour la démo et les
 * premiers vrais projets. Pas de groupes/middlewares/paramètres nommés
 * (roadmap v0.2) — volontairement minimal plutôt que de mal faire beaucoup.
 */
final class Router
{
    /** @var array<string, array<string, \Closure>> méthode => [chemin => handler] */
    private array $routes = [];

    public function get(string $path, \Closure $handler): self
    {
        $this->routes['GET'][$path] = $handler;
        return $this;
    }

    public function post(string $path, \Closure $handler): self
    {
        $this->routes['POST'][$path] = $handler;
        return $this;
    }

    public function dispatch(string $method, string $path): mixed
    {
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            echo 'Not found';
            return null;
        }

        return $handler();
    }
}
