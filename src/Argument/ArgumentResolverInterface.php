<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Argument;

use Mithridatem\Routing\Context\RequestContextInterface;

interface ArgumentResolverInterface
{
    /**
     * @param array<string, mixed> $routeParameters
     * @return array<string, mixed> Arguments passed by name to the handler.
     */
    public function resolve(callable $handler, array $routeParameters, RequestContextInterface $context): array;
}
