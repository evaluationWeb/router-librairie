<?php

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Discovery\ControllerDiscovery;
use Mithridatem\Routing\Loader\AttributeRouteLoader;
use Mithridatem\Routing\Router;
use Mithridatem\Routing\Tests\Fixtures\Discovery\Controller\HealthController;
use Mithridatem\Routing\Tests\Fixtures\Discovery\Controller\Nested\ReportController;
use Mithridatem\Routing\Tests\Support\FakeRequestContext;
use PHPUnit\Framework\TestCase;

final class ControllerDiscoveryTest extends TestCase
{
    private const CONTROLLERS = __DIR__ . '/../Fixtures/Discovery/Controller';
    private const NAMESPACE_PREFIX = 'Mithridatem\\Routing\\Tests\\Fixtures\\Discovery\\Controller';

    public function testDiscoversControllersRecursively(): void
    {
        $classes = (new ControllerDiscovery())->discover(self::CONTROLLERS, self::NAMESPACE_PREFIX);

        self::assertSame([
            HealthController::class,
            ReportController::class,
        ], $classes);
    }

    public function testLoadsDirectoryAndDispatchesControllerRoutes(): void
    {
        $router = new Router();
        (new AttributeRouteLoader())->loadDirectory($router, self::CONTROLLERS, self::NAMESPACE_PREFIX);

        self::assertSame('healthy', $router->dispatch(new FakeRequestContext('/health')));
        self::assertSame('report:42', $router->dispatch(new FakeRequestContext('/reports/42')));
    }

    public function testInvalidDirectoryThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ControllerDiscovery())->discover(__DIR__ . '/missing-directory', self::NAMESPACE_PREFIX);
    }

    public function testEmptyNamespaceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ControllerDiscovery())->discover(self::CONTROLLERS, '');
    }

    public function testDiscoveryDoesNotIncludeUnmappedClasses(): void
    {
        self::assertSame(
            [],
            (new ControllerDiscovery())->discover(self::CONTROLLERS, 'Nonexistent\\Controllers')
        );
    }
}
