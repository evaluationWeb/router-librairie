<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Loader;

use Mithridatem\Routing\Attribute\Route as RouteAttribute;
use Mithridatem\Routing\Attribute\RouteGroup as RouteGroupAttribute;
use Mithridatem\Routing\Group\GroupedRoute;
use Mithridatem\Routing\Discovery\ControllerDiscovery;
use Mithridatem\Routing\Handler\ControllerReference;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use ReflectionClass;
use ReflectionMethod;

final class AttributeRouteLoader
{
    /**
     * Register route attributes on public methods of one controller class.
     *
     * @param class-string $controllerClass
     */
    public function loadController(Router $router, string $controllerClass): void
    {
        $reflection = new ReflectionClass($controllerClass);

        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException(sprintf('Controller "%s" must be instantiable.', $controllerClass));
        }

        $groupAttribute = $reflection->getAttributes(RouteGroupAttribute::class)[0] ?? null;
        /** @var RouteGroupAttribute|null $group */
        $group = $groupAttribute?->newInstance();

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(RouteAttribute::class) as $attribute) {
                /** @var RouteAttribute $definition */
                $definition = $attribute->newInstance();

                $route = new Route(
                    methods: (array) $definition->method,
                    path: $definition->path,
                    handler: new ControllerReference($controllerClass, $method->getName()),
                    grants: $definition->grants,
                    middleware: $definition->middleware
                );

                $router->map($group === null ? $route : new GroupedRoute(
                    $route, $group->prefix, $group->grants, $group->middleware
                ));
            }
        }
    }

    /**
     * @param iterable<class-string> $controllerClasses
     */
    public function loadControllers(Router $router, iterable $controllerClasses): void
    {
        foreach ($controllerClasses as $controllerClass) {
            $this->loadController($router, $controllerClass);
        }
    }
    /**
     * Discover PSR-4 classes in a directory and register their route attributes.
     *
     * @param string $directory Absolute or relative filesystem path.
     * @param string $namespace Namespace corresponding to the directory.
     */
    public function loadDirectory(Router $router, string $directory, string $namespace): void
    {
        $classes = (new ControllerDiscovery())->discover($directory, $namespace);
        $this->loadControllers($router, $classes);
    }

}
