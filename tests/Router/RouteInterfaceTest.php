<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Router;

use Closure;
use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Exception\MethodNotAllowedException;
use Mithridatem\Routing\Exception\RouterException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Handler\ControllerReference;
use Mithridatem\Routing\Middleware\MiddlewareInterface;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\RouteCollection;
use Mithridatem\Routing\RouteInterface;
use Mithridatem\Routing\Router;
use Mithridatem\Routing\Http\Request;
use PHPUnit\Framework\TestCase;

final class RouteInterfaceTest extends TestCase
{
    public function testExistingRouteFactoriesImplementContract(): void
    {
        self::assertInstanceOf(RouteInterface::class, Route::get('/books', static fn () => 'ok'));
        self::assertInstanceOf(RouteInterface::class, Route::post('/books', static fn () => 'ok'));
        self::assertInstanceOf(RouteInterface::class, Route::controller('GET', '/books', 'BookController', 'index'));
    }

    public function testCustomRouteCanBeRegisteredMatchedAndRemoved(): void
    {
        $route = new CustomRouteForContractTest('/custom', ['GET'], static fn (): string => 'custom');
        $collection = new RouteCollection();
        $collection->add($route);

        self::assertSame([$route], $collection->all());
        self::assertSame($route, $collection->match('GET', '/custom')?->getRoute());
        self::assertSame(['GET'], $collection->allowedMethods('/custom'));

        $router = new Router($collection);
        self::assertSame('custom', $router->dispatch(new Request('/custom')));

        $collection->remove($route);
        self::assertSame([], $collection->all());
    }

    public function testCustomRouteSupportsGrantsAndMiddleware(): void
    {
        $events = [];
        $middleware = new class($events) implements MiddlewareInterface {
            public function __construct(private array &$events) {}
            public function process(RequestContextInterface $context, callable $next): mixed
            {
                $this->events[] = 'before';
                $result = $next($context);
                $this->events[] = 'after';
                return $result;
            }
        };

        $route = new CustomRouteForContractTest(
            '/secure',
            ['GET'],
            static fn (): string => 'secured',
            ['ROLE_USER'],
            [$middleware]
        );
        $router = new Router();
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_USER']));
        $router->map($route);

        self::assertSame('secured', $router->dispatch(new Request('/secure')));
        self::assertSame(['before', 'after'], $events);
    }

    public function testCustomRouteStillHonorsGrantDenial(): void
    {
        $router = new Router();
        $router->map(new CustomRouteForContractTest(
            '/secure', ['GET'], static fn (): string => 'secret', ['ROLE_ADMIN']
        ));
        $this->expectException(UnauthorizedException::class);
        $router->dispatch(new Request('/secure'));
    }

    public function testCustomRoutePreserves405AndDuplicateDetection(): void
    {
        $collection = new RouteCollection();
        $collection->add(new CustomRouteForContractTest('/books', ['GET'], static fn () => 'ok'));
        self::assertSame(['GET'], $collection->allowedMethods('/books'));

        $router = new Router($collection);
        try {
            $router->dispatch(new Request('/books', 'POST'));
            self::fail('Expected MethodNotAllowedException');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(['GET'], $exception->getAllowedMethods());
        }

        $this->expectException(RouterException::class);
        $collection->add(Route::get('/books', static fn () => 'duplicate'));
    }

    public function testExistingRouteMatchStillReturnsOriginalRouteObject(): void
    {
        $route = Route::get('/old', static fn () => 'legacy');
        $collection = new RouteCollection();
        $collection->add($route);

        self::assertSame($route, $collection->match('GET', '/old')?->getRoute());
        self::assertInstanceOf(Route::class, $collection->match('GET', '/old')?->getRoute());
    }
}

/** Example of an alternative route definition delegating its matching behavior. */
final class CustomRouteForContractTest implements RouteInterface
{
    private Route $inner;

    public function __construct(
        string $path,
        array $methods,
        Closure|ControllerReference $handler,
        array $grants = [],
        array $middleware = []
    ) {
        $this->inner = new Route($methods, $path, $handler, $grants, [], $middleware);
    }

    public function getPath(): string { return $this->inner->getPath(); }
    public function getMethods(): array { return $this->inner->getMethods(); }
    public function getHandler(): Closure|ControllerReference { return $this->inner->getHandler(); }
    public function getGrants(): array { return $this->inner->getGrants(); }
    public function getMiddleware(): array { return $this->inner->getMiddleware(); }
    public function getDefaultParameters(): array { return $this->inner->getDefaultParameters(); }
    public function matches(string $method, string $path): ?array { return $this->inner->matches($method, $path); }
    public function matchesPath(string $path): bool { return $this->inner->matchesPath($path); }
}
