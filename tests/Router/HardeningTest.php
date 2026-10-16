<?php

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Exception\MethodNotAllowedException;
use Mithridatem\Routing\Exception\RouterException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use Mithridatem\Routing\Tests\Support\FakeRequestContext;
use PHPUnit\Framework\TestCase;

final class HardeningTest extends TestCase
{
    public function testProtectedRouteDeniedByDefault(): void
    {
        $router = new Router();
        $router->map(Route::get('/private', fn () => 'secret', ['ROLE_ADMIN']));
        $this->expectException(UnauthorizedException::class);
        $router->dispatch(new FakeRequestContext('/private'));
    }

    public function testPublicRouteStillWorks(): void
    {
        $router = new Router();
        $router->map(Route::get('/', fn () => 'ok'));
        self::assertSame('ok', $router->dispatch(new FakeRequestContext('/')));
    }

    public function testLiteralRegexCharactersAreEscaped(): void
    {
        $router = new Router();
        $router->map(Route::get('/v1.0/{id}', fn (string $id) => $id));
        self::assertSame('42', $router->dispatch(new FakeRequestContext('/v1.0/42')));
        $this->expectException(\Mithridatem\Routing\Exception\RouteNotFoundException::class);
        $router->dispatch(new FakeRequestContext('/v1X0/42'));
    }

    public function testBasePathDoesNotMatchPartialSegment(): void
    {
        $router = new Router();
        $router->setBasePath('/app');
        $router->map(Route::get('/application/status', fn () => 'ok'));
        self::assertSame('ok', $router->dispatch(new FakeRequestContext('/application/status')));
    }

    public function testNamedParametersAreBoundByName(): void
    {
        $router = new Router();
        $router->map(Route::get('/books/{id}/{slug}', fn (string $slug, string $id) => "$id:$slug"));
        self::assertSame('42:hello', $router->dispatch(new FakeRequestContext('/books/42/hello')));
    }

    public function testWrongMethodProduces405(): void
    {
        $router = new Router();
        $router->map(Route::get('/books', fn () => 'ok'));
        try {
            $router->dispatch(new FakeRequestContext('/books', 'POST'));
            self::fail('Expected 405');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(405, $exception->getCode());
            self::assertSame(['GET'], $exception->getAllowedMethods());
        }
    }

    public function testDuplicateRoutesAreRejected(): void
    {
        $router = new Router();
        $router->map(Route::get('/books', fn () => 'ok'));
        $this->expectException(RouterException::class);
        $router->map(Route::get('/books', fn () => 'duplicate'));
    }
}
