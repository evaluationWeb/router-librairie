<?php

namespace Mithridatem\Routing;

class RouteMatch
{
    public function __construct(
        private readonly RouteInterface $route,
        private readonly array $parameters
    ) {
    }

    public function getRoute(): RouteInterface
    {
        return $this->route;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}