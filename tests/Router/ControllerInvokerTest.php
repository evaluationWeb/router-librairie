<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Argument\DefaultArgumentResolver;
use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Context\NativeRequestContext;
use Mithridatem\Routing\Controller\ControllerInvoker;
use Mithridatem\Routing\Controller\DefaultControllerResolver;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\RouteMatch;
use PHPUnit\Framework\TestCase;

final class ControllerInvokerTest extends TestCase
{
    public function testInvokesClosureWithNamedRouteParameters(): void
    {
        $invoker = new ControllerInvoker(
            new DefaultControllerResolver(),
            new DefaultArgumentResolver(),
            new ArrayGrantChecker([])
        );

        $route = Route::get('/books/{id}', static fn (string $id): string => 'book-' . $id);

        self::assertSame(
            'book-42',
            $invoker->invoke(new RouteMatch($route, ['id' => '42']), new NativeRequestContext('/books/42', 'GET'))
        );
    }

    public function testLegacyScalarCoercionIsPreserved(): void
    {
        $invoker = new ControllerInvoker(
            new DefaultControllerResolver(),
            new DefaultArgumentResolver(),
            new ArrayGrantChecker([])
        );

        $route = Route::get('/books/{id}', static fn (int $id): int => $id);

        self::assertSame(
            42,
            $invoker->invoke(new RouteMatch($route, ['id' => '42']), new NativeRequestContext('/books/42', 'GET'))
        );
    }

    public function testAuthorizationPrecedesInvocation(): void
    {
        $called = false;
        $invoker = new ControllerInvoker(
            new DefaultControllerResolver(),
            new DefaultArgumentResolver(),
            new ArrayGrantChecker(['ROLE_USER'])
        );

        $route = Route::get('/admin', static function () use (&$called): string {
            $called = true;
            return 'secret';
        }, ['ROLE_ADMIN']);

        try {
            $invoker->invoke(new RouteMatch($route, []), new NativeRequestContext('/admin', 'GET'));
            self::fail('Expected an unauthorized exception.');
        } catch (UnauthorizedException $exception) {
            self::assertSame(403, $exception->getCode());
            self::assertFalse($called);
        }
    }
}
