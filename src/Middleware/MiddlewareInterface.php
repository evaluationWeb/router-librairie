<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Middleware;

use Mithridatem\Routing\Context\RequestContextInterface;

interface MiddlewareInterface
{
    /**
     * Continue by calling $next($context), or short-circuit by returning a result.
     * @param callable(RequestContextInterface): mixed $next
     */
    public function process(RequestContextInterface $context, callable $next): mixed;
}
