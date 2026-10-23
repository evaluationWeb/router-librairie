<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Argument;

use Closure;
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Exception\RouterException;
use Mithridatem\Routing\Http\RequestInterface;
use ReflectionFunction;
use ReflectionNamedType;

final class DefaultArgumentResolver implements ArgumentResolverInterface
{
    public function resolve(callable $handler, array $routeParameters, RequestContextInterface $context): array
    {
        $arguments = $routeParameters;
        $reflection = new ReflectionFunction(Closure::fromCallable($handler));

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = $type->getName();
            if (!is_a($class, RequestInterface::class, true)) {
                continue;
            }

            if ($parameter->isVariadic() || $parameter->isPassedByReference()) {
                throw new RouterException('Request injection requires a non-variadic parameter passed by value.');
            }

            if (!$context instanceof $class) {
                throw new RouterException(sprintf(
                    'Cannot inject $%s: expected %s, received %s. Pass a compatible RequestInterface to dispatch().',
                    $parameter->getName(),
                    $class,
                    get_debug_type($context)
                ));
            }

            if (array_key_exists($parameter->getName(), $routeParameters)) {
                throw new RouterException(sprintf(
                    'Route parameter "%s" conflicts with a request injection parameter.',
                    $parameter->getName()
                ));
            }

            $arguments[$parameter->getName()] = $context;
        }

        // Preserve PHP's existing named-argument, default and variadic semantics.
        return $arguments;
    }
}
