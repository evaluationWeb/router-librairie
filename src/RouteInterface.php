<?php

declare(strict_types=1);

namespace Mithridatem\Routing;

use Closure;
use Mithridatem\Routing\Handler\ControllerReference;
use Mithridatem\Routing\Middleware\MiddlewareInterface;

/**
 * Structural contract for route definitions understood by RouteCollection and Router.
 *
 * Implementations own their matching strategy. Grants and middleware remain
 * optional configuration, not different route types.
 */
interface RouteInterface
{
    public function getPath(): string;

    /** @return list<string> */
    public function getMethods(): array;

    /** @return Closure|ControllerReference */
    public function getHandler(): Closure|ControllerReference;

    /** @return list<string> */
    public function getGrants(): array;

    /** @return list<MiddlewareInterface|class-string<MiddlewareInterface>> */
    public function getMiddleware(): array;

    /** @return array<string, mixed> */
    public function getDefaultParameters(): array;

    /** @return array<string, mixed>|null Null when the route does not match. */
    public function matches(string $method, string $path): ?array;

    public function matchesPath(string $path): bool;
}
