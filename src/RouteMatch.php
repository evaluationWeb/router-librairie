<?php

namespace Mithridatem\Routing;

class RouteMatch
{
    public function __construct(
        private readonly Route $route,
        private readonly array $parameters
    ) {
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}