<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Attribute;

use Attribute;
use Mithridatem\Routing\Middleware\MiddlewareInterface;

/** Optional class-level route defaults. */
#[Attribute(Attribute::TARGET_CLASS)]
final class RouteGroup
{
    public function __construct(
        public readonly string $prefix = '/',
        public readonly array $grants = [],
        public readonly array $middleware = []
    ) {
        foreach ($middleware as $entry) {
            if (!is_string($entry) || !is_a($entry, MiddlewareInterface::class, true)) {
                throw new \InvalidArgumentException('Group middleware must be MiddlewareInterface class names.');
            }
        }
    }
}
