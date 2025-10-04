<?php

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Handler\ControllerReference;
use Mithridatem\Routing\Router;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Tests\Support\FakeControllerResolver;
use Mithridatem\Routing\Tests\Support\FakeGrantChecker;
use Mithridatem\Routing\Tests\Support\FakeRequestContext;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testDispatchClosureRouteReturnsValue(): void
    {
        $router = new Router();
        $router->map(Route::get('/hello', fn () => 'ok'));

        $result = $router->dispatch(new FakeRequestContext('/hello'));

        self::assertSame('ok', $result);
    }

    public function testDispatchControllerWithGrants(): void
    {
        $controller = new class {
            public function dashboard(): string
            {
                return 'dashboard';
            }
        };

        $router = new Router();
        $router->setControllerResolver(new FakeControllerResolver([
            get_class($controller) => $controller,
        ]));
        $router->setGrantChecker(new FakeGrantChecker(['ROLE_ADMIN']));

        $router->map(Route::controller(
            'GET',
            '/admin',
            get_class($controller),
            'dashboard',
            ['ROLE_ADMIN']
        ));

        $result = $router->dispatch(new FakeRequestContext('/admin'));

        self::assertSame('dashboard', $result);
    }

    public function testDispatchUnauthorizedThrows(): void
    {
        $router = new Router();
        $router->setGrantChecker(new FakeGrantChecker(['ROLE_USER']));
        $router->map(Route::get('/secure', fn () => 'secret', ['ROLE_ADMIN']));

        $this->expectException(UnauthorizedException::class);
        $router->dispatch(new FakeRequestContext('/secure'));
    }

    public function testBasePathIsRemoved(): void
    {
        $router = new Router();
        $router->setBasePath('/app');
        $router->map(Route::get('/status', fn () => 'ok'));

        $result = $router->dispatch(new FakeRequestContext('/app/status'));

        self::assertSame('ok', $result);
    }
}