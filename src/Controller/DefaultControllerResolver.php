<?php

namespace Mithridatem\Routing\Controller;

use Mithridatem\Routing\Exception\RouterException;
use Mithridatem\Routing\Handler\ControllerReference;

class DefaultControllerResolver implements ControllerResolverInterface
{
    public function resolve(ControllerReference $controller): callable
    {
        $className = $controller->getClassName();

        if (!class_exists($className)) {
            throw new RouterException(sprintf('Controller "%s" not found.', $className));
        }

        $instance = new $className();
        $method = $controller->getMethodName();

        if (!method_exists($instance, $method)) {
            throw new RouterException(sprintf('Method "%s" not found on controller "%s".', $method, $className));
        }

        return [$instance, $method];
    }
}