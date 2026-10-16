<?php

namespace Mithridatem\Routing;

class RouteCollection
{
    /** @var Route[] */
    private array $routes = [];

    public function add(Route $route): void
    {
        foreach ($this->routes as $existing) {
            if ($existing->getPath() === $route->getPath()
                && array_intersect($existing->getMethods(), $route->getMethods()) !== []) {
                throw new \Mithridatem\Routing\Exception\RouterException(
                    'Duplicate route for path ' . $route->getPath()
                );
            }
        }
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

    /** @return string[] */
    public function allowedMethods(string $path): array
    {
        $methods = [];
        foreach ($this->routes as $route) {
            if ($route->matchesPath($path)) {
                $methods = array_merge($methods, $route->getMethods());
            }
        }
        return array_values(array_unique($methods));
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