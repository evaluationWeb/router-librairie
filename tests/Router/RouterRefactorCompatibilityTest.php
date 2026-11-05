<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Argument\ArgumentResolverInterface;
use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Context\NativeRequestContext;
use Mithridatem\Routing\Controller\ControllerResolverInterface;
use Mithridatem\Routing\Handler\ControllerReference;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouterRefactorCompatibilityTest extends TestCase
{
    public function testSettersStillAffectFollowingDispatches(): void
    {
        $router = new Router();
        $router->setControllerResolver(new class implements ControllerResolverInterface {
            public function resolve(ControllerReference $controller): callable
            {
                return static fn (string $id): string => 'resolved-' . $id;
            }
        });
        $router->setArgumentResolver(new class implements ArgumentResolverInterface {
            public function resolve(callable $handler, array $routeParameters, \Mithridatem\Routing\Context\RequestContextInterface $context): array
            {
                return ['id' => 'override'];
            }
        });
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_USER']));
        $router->map(Route::controller('GET', '/books/{id}', 'UnusedController', 'show', ['ROLE_USER']));

        self::assertSame('resolved-override', $router->dispatch(new NativeRequestContext('/books/42', 'GET')));
    }

    public function testRoutePathNormalizationRemainsCompatible(): void
    {
        $router = new Router();
        $router->setBasePath('app/');
        $router->map(Route::get('books/', static fn (): string => 'ok'));

        self::assertSame('/app', $router->getBasePath());
        self::assertSame('ok', $router->dispatch(new NativeRequestContext('/app/books/', 'GET')));
    }
}
