<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Middleware;

interface MiddlewareResolverInterface
{
    /** @param class-string<MiddlewareInterface> $middleware */
    public function resolve(string $middleware): MiddlewareInterface;
}
