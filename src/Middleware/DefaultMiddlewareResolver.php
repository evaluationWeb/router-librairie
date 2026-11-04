<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Middleware;

final class DefaultMiddlewareResolver implements MiddlewareResolverInterface
{
    public function resolve(string $middleware): MiddlewareInterface
    {
        if (!is_a($middleware, MiddlewareInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Middleware "%s" must implement %s.',
                $middleware,
                MiddlewareInterface::class
            ));
        }

        return new $middleware();
    }
}
