<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Controller;

use Mithridatem\Routing\Argument\ArgumentResolverInterface;
use Mithridatem\Routing\Auth\GrantCheckerInterface;
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Handler\ControllerReference;
use Mithridatem\Routing\RouteMatch;

/**
 * Invokes the matched route handler after checking its grants.
 *
 * The router remains responsible for matching and middleware orchestration.
 */
final class ControllerInvoker
{
    public function __construct(
        private ControllerResolverInterface $controllerResolver,
        private ArgumentResolverInterface $argumentResolver,
        private GrantCheckerInterface $grantChecker
    ) {
    }

    public function invoke(RouteMatch $match, RequestContextInterface $request): mixed
    {
        $route = $match->getRoute();

        if (!$this->grantChecker->isGranted($route->getGrants())) {
            throw new UnauthorizedException('Access denied for this route.', 403);
        }

        $handler = $route->getHandler();
        $callable = $handler instanceof ControllerReference
            ? $this->controllerResolver->resolve($handler)
            : $handler;

        $arguments = $this->argumentResolver->resolve($callable, $match->getParameters(), $request);

        return $callable(...$arguments);
    }
}
