<?php

namespace Mithridatem\Routing\Tests\Support;

use Mithridatem\Routing\Controller\ControllerResolverInterface;
use Mithridatem\Routing\Exception\RouterException;
use Mithridatem\Routing\Handler\ControllerReference;

class FakeControllerResolver implements ControllerResolverInterface
{
    /** @param array<string, object> $registry */
    public function __construct(private array $registry = [])
    {
    }

    public function resolve(ControllerReference $controller): callable
    {
        $class = $controller->getClassName();
        $method = $controller->getMethodName();

        $instance = $this->registry[$class] ?? null;

        if ($instance === null) {
            throw new RouterException(sprintf('No fake controller registered for %s', $class));
        }

        if (!method_exists($instance, $method)) {
            throw new RouterException(sprintf('Method %s missing on fake controller %s', $method, $class));
        }

        return [$instance, $method];
    }
}