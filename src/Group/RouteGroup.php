<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Group;

use Mithridatem\Routing\Route;
use Mithridatem\Routing\RouteInterface;
use Mithridatem\Routing\Router;
use Mithridatem\Routing\Middleware\MiddlewareInterface;

/**
 * Fluent group configuration. Routes are registered after the callback completes.
 */
final class RouteGroup
{
    /** @var list<RouteInterface> */
    private array $routes = [];
    private array $grants = [];
    private array $middleware = [];

    public function __construct(private Router $router, private string $prefix) {}

    public function grants(array $grants): self
    {
        $this->grants = $grants;
        return $this;
    }

    public function middleware(MiddlewareInterface|string ...$middleware): self
    {
        $this->middleware = [...$this->middleware, ...$middleware];
        return $this;
    }

    public function map(RouteInterface $route): self
    {
        $this->routes[] = $route;
        return $this;
    }

    public function mapController(string $method, string $path, string $class, string $action, array $grants = []): self
    {
        return $this->map(Route::controller($method, $path, $class, $action, $grants));
    }

    public function register(): void
    {
        foreach ($this->routes as $route) {
            $this->router->map(new GroupedRoute($route, $this->prefix, $this->grants, $this->middleware));
        }
    }
}
