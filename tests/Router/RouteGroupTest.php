<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Attribute\Route as RouteAttribute;
use Mithridatem\Routing\Attribute\RouteGroup as RouteGroupAttribute;
use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Exception\RouteNotFoundException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Group\RouteGroup;
use Mithridatem\Routing\Http\Request;
use Mithridatem\Routing\Loader\AttributeRouteLoader;
use Mithridatem\Routing\Middleware\MiddlewareInterface;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouteGroupTest extends TestCase
{
    public function testPrefixAndGroupGrantsAreInheritedByDefault(): void
    {
        $router = new Router();
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_USER']));
        $router->group('/api', static function (RouteGroup $group): void {
            $group->grants(['ROLE_USER']);
            $group->map(Route::get('/profile', static fn (): string => 'profile'));
        });

        self::assertSame('profile', $router->dispatch(new Request('/api/profile')));
        self::assertSame('/api/profile', $router->getRoutes()->all()[0]->getPath());
        $this->expectException(RouteNotFoundException::class);
        $router->dispatch(new Request('/apiary/profile'));
    }

    public function testExplicitRouteGrantsReplaceGroupGrantsRatherThanMerge(): void
    {
        $router = new Router();
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_USER']));
        $router->group('/api', static function (RouteGroup $group): void {
            $group->grants(['ROLE_USER']);
            $group->map(Route::get('/admin', static fn (): string => 'admin', ['ROLE_ADMIN']));
        });

        self::assertSame(['ROLE_ADMIN'], $router->getRoutes()->all()[0]->getGrants());
        $this->expectException(UnauthorizedException::class);
        $router->dispatch(new Request('/api/admin'));
    }

    public function testCumulativeRolesAreCompatibleWithGrantCheckerOrSemantics(): void
    {
        $router = new Router();
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_USER', 'ROLE_ADMIN']));
        $router->group('/api', static function (RouteGroup $group): void {
            $group->grants(['ROLE_USER']);
            $group->map(Route::get('/admin', static fn (): string => 'ok', ['ROLE_ADMIN']));
        });

        self::assertSame('ok', $router->dispatch(new Request('/api/admin')));
    }

    public function testMiddlewareOrderIsGlobalGroupRoute(): void
    {
        $events = [];
        $router = new Router();
        $router->addMiddleware(new GroupRecordingMiddleware('global', $events));
        $router->group('/web', static function (RouteGroup $group) use (&$events): void {
            $group->middleware(new GroupRecordingMiddleware('group', $events));
            $group->map(Route::post('/save', static function () use (&$events): string {
                $events[] = 'controller';
                return 'ok';
            })->withMiddleware(new GroupRecordingMiddleware('route', $events)));
        });
        self::assertSame('ok', $router->dispatch(new Request('/web/save', 'POST')));
        self::assertSame([
            'global-before', 'group-before', 'route-before', 'controller',
            'route-after', 'group-after', 'global-after'
        ], $events);
    }

    public function testGroupConfigurationDoesNotAffectExistingRoutes(): void
    {
        $router = new Router();
        $router->map(Route::get('/public', static fn (): string => 'public'));
        $router->group('/admin', static function (RouteGroup $group): void {
            $group->grants(['ROLE_ADMIN']);
            $group->map(Route::get('/private', static fn (): string => 'private'));
        });
        self::assertSame('public', $router->dispatch(new Request('/public')));
        self::assertSame([], $router->getRoutes()->all()[0]->getGrants());
    }

    public function testClassAttributeProvidesPrefixAndInheritedGrants(): void
    {
        $router = new Router();
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_USER']));
        (new AttributeRouteLoader())->loadController($router, GroupedAttributeController::class);

        self::assertSame('profile', $router->dispatch(new Request('/api/profile')));
        self::assertSame('admin', $router->getRoutes()->all()[1]->getPath() === '/api/admin' ? 'admin' : 'unexpected');
        $this->expectException(UnauthorizedException::class);
        $router->dispatch(new Request('/api/admin'));
    }

    public function testClassAttributeMiddlewareExecutesBeforeAction(): void
    {
        $router = new Router();
        (new AttributeRouteLoader())->loadController($router, GroupedMiddlewareController::class);
        self::assertSame('wrapped:ok', $router->dispatch(new Request('/v1/health')));
    }
}

final class GroupRecordingMiddleware implements MiddlewareInterface
{
    public function __construct(private string $name, private array &$events) {}
    public function process(RequestContextInterface $context, callable $next): mixed
    {
        $this->events[] = $this->name . '-before';
        $value = $next($context);
        $this->events[] = $this->name . '-after';
        return $value;
    }
}

final class GroupWrappingMiddleware implements MiddlewareInterface
{
    public function process(RequestContextInterface $context, callable $next): mixed
    {
        return 'wrapped:' . $next($context);
    }
}

#[RouteGroupAttribute(prefix: '/api', grants: ['ROLE_USER'])]
final class GroupedAttributeController
{
    #[RouteAttribute(path: '/profile')]
    public function profile(): string { return 'profile'; }

    #[RouteAttribute(path: '/admin', grants: ['ROLE_ADMIN'])]
    public function admin(): string { return 'admin'; }
}

#[RouteGroupAttribute(prefix: '/v1', middleware: [GroupWrappingMiddleware::class])]
final class GroupedMiddlewareController
{
    #[RouteAttribute(path: '/health')]
    public function health(): string { return 'ok'; }
}
