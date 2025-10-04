<?php

namespace Mithridatem\Routing;

class RouteCollection
{
    /** @var Route[] */
    private array $routes = [];

    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    public function remove(Route $route): void
    {
        foreach ($this->routes as $index => $registeredRoute) {
            if ($registeredRoute === $route) {
                unset($this->routes[$index]);
                $this->routes = array_values($this->routes);
                break;
            }
        }
    }

    /**
     * @return Route[]
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function match(string $method, string $path): ?RouteMatch
    {
        foreach ($this->routes as $route) {
            $parameters = $route->matches($method, $path);

            if ($parameters !== null) {
                return new RouteMatch($route, $parameters);
            }
        }

        return null;
    }
}