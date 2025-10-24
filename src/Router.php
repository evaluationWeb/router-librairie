<?php

namespace Mithridatem\Routing;

use Mithridatem\Routing\Auth\AllowAllGrantChecker;
use Mithridatem\Routing\Auth\GrantCheckerInterface;
use Mithridatem\Routing\Context\NativeRequestContext;
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Controller\ControllerResolverInterface;
use Mithridatem\Routing\Controller\DefaultControllerResolver;
use Mithridatem\Routing\Exception\RouteNotFoundException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Handler\ControllerReference;

class Router
{
    private RouteCollection $routes;
    private ?RequestContextInterface $defaultContext = null;
    private string $basePath = '/';
    private ControllerResolverInterface $controllerResolver;
    private GrantCheckerInterface $grantChecker;

    public function __construct(
        ?RouteCollection $routes = null,
        ?ControllerResolverInterface $controllerResolver = null,
        ?GrantCheckerInterface $grantChecker = null
    ) {
        $this->routes = $routes ?? new RouteCollection();
        $this->controllerResolver = $controllerResolver ?? new DefaultControllerResolver();
        $this->grantChecker = $grantChecker ?? new AllowAllGrantChecker();
    }

    public function setDefaultContext(RequestContextInterface $context): void
    {
        $this->defaultContext = $context;
    }

    public function setControllerResolver(ControllerResolverInterface $resolver): void
    {
        $this->controllerResolver = $resolver;
    }

    public function setGrantChecker(GrantCheckerInterface $grantChecker): void
    {
        $this->grantChecker = $grantChecker;
    }

    public function setBasePath(string $basePath): void
    {
        $normalized = $basePath === '/' ? '/' : '/' . trim($basePath, '/');
        $this->basePath = $normalized;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function map(Route $route): self
    {
        $this->routes->add($route);

        return $this;
    }

    public function mapController(
        string $method,
        string $path,
        string $controllerClass,
        string $controllerMethod,
        array $grants = []
    ): self {
        $this->map(Route::controller($method, $path, $controllerClass, $controllerMethod, $grants));

        return $this;
    }

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    public function dispatch(?RequestContextInterface $context = null): mixed
    {
        $context = $context ?? $this->defaultContext ?? NativeRequestContext::fromGlobals();
        $path = $this->stripBasePath($context->getPath());
        $match = $this->routes->match($context->getMethod(), $path);

        if ($match === null) {
            throw new RouteNotFoundException(sprintf('No route found for %s %s', $context->getMethod(), $path), 404);
        }

        $route = $match->getRoute();

        if (!$this->grantChecker->isGranted($route->getGrants())) {
            throw new UnauthorizedException('Access denied for this route.', 403);
        }

        $handler = $route->getHandler();
        $callable = $handler instanceof ControllerReference
            ? $this->controllerResolver->resolve($handler)
            : $handler;

        return $callable(...array_values($match->getParameters()));
    }

    private function stripBasePath(string $path): string
    {
        $normalized = $this->normalizePath($path);

        if ($this->basePath === '/' || $this->basePath === '') {
            return $normalized;
        }

        if (str_starts_with($normalized, $this->basePath)) {
            $stripped = substr($normalized, strlen($this->basePath)) ?: '/';
            return $this->normalizePath($stripped);
        }

        return $normalized;
    }

    private function normalizePath(string $path): string
    {
        $clean = '/' . ltrim($path, '/');
        return rtrim($clean, '/') ?: '/';
    }
}