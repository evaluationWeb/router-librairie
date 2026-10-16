<?php

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Exception\RouteNotFoundException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Loader\AttributeRouteLoader;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use Mithridatem\Routing\Tests\Support\AttributeDemoController;
use Mithridatem\Routing\Tests\Support\FakeRequestContext;
use PHPUnit\Framework\TestCase;

final class AttributeRouteLoaderTest extends TestCase
{
    public function testLoadsRepeatedAndMultipleMethodAttributes(): void
    {
        $router = new Router();
        (new AttributeRouteLoader())->loadController($router, AttributeDemoController::class);

        self::assertCount(3, $router->getRoutes()->all());
        self::assertSame('products', $router->dispatch(new FakeRequestContext('/products')));
        self::assertSame('products', $router->dispatch(new FakeRequestContext('/catalog', 'POST')));
    }

    public function testLoadsDynamicParametersAndGrants(): void
    {
        $router = new Router(grantChecker: new ArrayGrantChecker(['ROLE_ADMIN']));
        (new AttributeRouteLoader())->loadControllers($router, [AttributeDemoController::class]);

        self::assertSame('42', $router->dispatch(new FakeRequestContext('/products/42')));
    }

    public function testProtectedAttributeRouteDeniedByDefault(): void
    {
        $router = new Router();
        (new AttributeRouteLoader())->loadController($router, AttributeDemoController::class);

        $this->expectException(UnauthorizedException::class);
        $router->dispatch(new FakeRequestContext('/products/42'));
    }

    public function testUnannotatedMethodsAreNotRegistered(): void
    {
        $router = new Router();
        (new AttributeRouteLoader())->loadController($router, AttributeDemoController::class);

        $this->expectException(RouteNotFoundException::class);
        $router->dispatch(new FakeRequestContext('/withoutRoute'));
    }

    public function testManualAndAttributeRoutesCoexist(): void
    {
        $router = new Router();
        $router->map(Route::get('/manual', fn () => 'manual'));
        (new AttributeRouteLoader())->loadController($router, AttributeDemoController::class);

        self::assertSame('manual', $router->dispatch(new FakeRequestContext('/manual')));
        self::assertSame('products', $router->dispatch(new FakeRequestContext('/products')));
    }
}
