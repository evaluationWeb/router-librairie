<?php

namespace Mithridatem\Routing;

use Mithridatem\Routing\Argument\ArgumentResolverInterface;
use Mithridatem\Routing\Argument\DefaultArgumentResolver;
use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Auth\GrantCheckerInterface;
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Controller\ControllerResolverInterface;
use Mithridatem\Routing\Controller\DefaultControllerResolver;
use Mithridatem\Routing\Controller\ControllerInvoker;
use Mithridatem\Routing\Exception\RouteNotFoundException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Handler\ControllerReference;
use Mithridatem\Routing\Group\RouteGroup;
use Mithridatem\Routing\Http\Request;
use Mithridatem\Routing\Middleware\DefaultMiddlewareResolver;
use Mithridatem\Routing\Middleware\MiddlewareInterface;
use Mithridatem\Routing\Middleware\MiddlewarePipeline;
use Mithridatem\Routing\Middleware\MiddlewareResolverInterface;
use Mithridatem\Routing\Path\PathNormalizer;

class Router
{
    private RouteCollection $routes;
    private PathNormalizer $pathNormalizer;
    private ?RequestContextInterface $defaultContext = null;
    private string $basePath = '/';
    private ControllerResolverInterface $controllerResolver;
    private GrantCheckerInterface $grantChecker;
    private ArgumentResolverInterface $argumentResolver;
    private MiddlewareResolverInterface $middlewareResolver;
    /** @var list<MiddlewareInterface|class-string<MiddlewareInterface>> */
    private array $middleware = [];

    public function __construct(
        ?RouteCollection $routes = null,
        ?ControllerResolverInterface $controllerResolver = null,
        ?GrantCheckerInterface $grantChecker = null
    ) {
        $this->routes = $routes ?? new RouteCollection();
        $this->pathNormalizer = new PathNormalizer();
        $this->controllerResolver = $controllerResolver ?? new DefaultControllerResolver();
        $this->grantChecker = $grantChecker ?? new ArrayGrantChecker([]);
        $this->argumentResolver = new DefaultArgumentResolver();
        $this->middlewareResolver = new DefaultMiddlewareResolver();
    }

    public function setDefaultContext(RequestContextInterface $context): void
    {
        $this->defaultContext = $context;
    }

    public function setArgumentResolver(ArgumentResolverInterface $resolver): void
    {
        $this->argumentResolver = $resolver;
    }

    public function setControllerResolver(ControllerResolverInterface $resolver): void
    {
        $this->controllerResolver = $resolver;
    }

    public function setGrantChecker(GrantCheckerInterface $grantChecker): void
    {
        $this->grantChecker = $grantChecker;
    }

    public function setMiddlewareResolver(MiddlewareResolverInterface $resolver): void
    {
        $this->middlewareResolver = $resolver;
    }

    public function addMiddleware(MiddlewareInterface|string $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = $this->pathNormalizer->normalizeBasePath($basePath);
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function map(RouteInterface $route): self
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

    /** Configure and register a group of routes without changing existing map() behavior. */
    public function group(string $prefix, callable $configure): self
    {
        $group = new RouteGroup($this, $prefix);
        $configure($group);
        $group->register();

        return $this;
    }

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    public function dispatch(?RequestContextInterface $context = null): mixed
    {
        $context = $context ?? $this->defaultContext ?? Request::fromGlobals();
        $path = $this->pathNormalizer->stripBasePath($context->getPath(), $this->basePath);
        $match = $this->routes->match($context->getMethod(), $path);

        if ($match === null) {
            if ($this->routes->allowedMethods($path) !== []) {
                throw new \Mithridatem\Routing\Exception\MethodNotAllowedException($this->routes->allowedMethods($path));
            }
            throw new RouteNotFoundException(sprintf('No route found for %s %s', $context->getMethod(), $path), 404);
        }

        $route = $match->getRoute();

        $invoker = new ControllerInvoker(
            $this->controllerResolver,
            $this->argumentResolver,
            $this->grantChecker
        );

        $destination = static fn (RequestContextInterface $request): mixed => $invoker->invoke($match, $request);

        return (new MiddlewarePipeline($this->middlewareResolver))->process(
            $context,
            [...$this->middleware, ...$route->getMiddleware()],
            $destination
        );
    }

}
