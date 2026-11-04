<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Attribute\Route as RouteAttribute;
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Http\Request;
use Mithridatem\Routing\Loader\AttributeRouteLoader;
use Mithridatem\Routing\Middleware\MiddlewareInterface;
use Mithridatem\Routing\Middleware\MiddlewareResolverInterface;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use PHPUnit\Framework\TestCase;

final class MiddlewarePipelineTest extends TestCase
{
    public function testGlobalAndRouteMiddlewareOrderAndGrantCheck(): void
    {
        $events = [];
        $router = new Router();
        $router->addMiddleware(new RecordingMiddleware('global', $events));
        $router->map(Route::get('/hello', static function () use (&$events) {
            $events[] = 'controller';
            return 'ok';
        })->withMiddleware(new RecordingMiddleware('route', $events)));

        self::assertSame('ok', $router->dispatch(new Request('/hello')));
        self::assertSame(
            ['global-before', 'route-before', 'controller', 'route-after', 'global-after'],
            $events
        );
    }

    public function testMiddlewareCanStopControllerAndGrantCheck(): void
    {
        $called = false;
        $router = new Router();
        $router->addMiddleware(new class implements MiddlewareInterface {
            public function process(RequestContextInterface $context, callable $next): mixed
            {
                return 'blocked';
            }
        });
        $router->map(Route::get('/locked', static function () use (&$called) {
            $called = true;
            return 'unexpected';
        }, ['ROLE_ADMIN']));

        self::assertSame('blocked', $router->dispatch(new Request('/locked')));
        self::assertFalse($called);
    }

    public function testRoutesWithoutMiddlewareContinueToWorkWithLegacyContext(): void
    {
        $router = new Router();
        $router->map(Route::get('/old', static fn () => 'legacy'));
        $context = new class implements RequestContextInterface {
            public function getPath(): string { return '/old'; }
            public function getMethod(): string { return 'GET'; }
            public function getBearerToken(): ?string { return null; }
        };

        self::assertSame('legacy', $router->dispatch($context));
    }

    public function testNamedMiddlewareUsesResolverAndRouteAttribute(): void
    {
        $router = new Router();
        $router->setMiddlewareResolver(new class implements MiddlewareResolverInterface {
            public function resolve(string $middleware): MiddlewareInterface
            {
                TestCase::assertSame(NamedTestMiddleware::class, $middleware);
                return new NamedTestMiddleware();
            }
        });
        (new AttributeRouteLoader())->loadController($router, MiddlewareDemoController::class);

        self::assertSame('wrapped:controller', $router->dispatch(new Request('/middleware-demo')));
    }

    public function testGrantDenialRemainsEffectiveAfterMiddleware(): void
    {
        $called = false;
        $router = new Router();
        $router->addMiddleware(new class implements MiddlewareInterface {
            public function process(RequestContextInterface $context, callable $next): mixed
            {
                return $next($context);
            }
        });
        $router->map(Route::get('/private', static function () use (&$called) {
            $called = true;
            return 'private';
        }, ['ROLE_ADMIN']));

        $this->expectException(\Mithridatem\\Routing\\Exception\\UnauthorizedException::class);
        try {
            $router->dispatch(new Request('/private'));
        } finally {
            self::assertFalse($called);
        }
    }

    public function testMiddlewareExceptionPropagates(): void
    {
        $router = new Router();
        $router->map(Route::get('/explode', static fn () => 'unused')
            ->withMiddleware(new class implements MiddlewareInterface {
                public function process(RequestContextInterface $context, callable $next): mixed
                {
                    throw new \RuntimeException('middleware failure');
                }
            }));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('middleware failure');
        $router->dispatch(new Request('/explode'));
    }

    public function testGlobalMiddlewareReceivesAlreadyMatchedRoute(): void
    {
        $router = new Router();
        $router->addMiddleware(new class implements MiddlewareInterface {
            public function process(RequestContextInterface $context, callable $next): mixed
            {
                return 'prefix:' . $next($context);
            }
        });
        $router->map(Route::get('/ok', static fn () => 'ok'));
        self::assertSame('prefix:ok', $router->dispatch(new Request('/ok')));
    }
}

final class RecordingMiddleware implements MiddlewareInterface
{
    public function __construct(private string $name, private array &$events) {}

    public function process(RequestContextInterface $context, callable $next): mixed
    {
        $this->events[] = $this->name . '-before';
        $result = $next($context);
        $this->events[] = $this->name . '-after';
        return $result;
    }
}

final class NamedTestMiddleware implements MiddlewareInterface
{
    public function process(RequestContextInterface $context, callable $next): mixed
    {
        return 'wrapped:' . $next($context);
    }
}

final class MiddlewareDemoController
{
    #[RouteAttribute(path: '/middleware-demo', middleware: [NamedTestMiddleware::class])]
    public function index(): string
    {
        return 'controller';
    }
}
