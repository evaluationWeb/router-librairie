<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Middleware;

use Mithridatem\Routing\Context\RequestContextInterface;

final class MiddlewarePipeline
{
    public function __construct(private MiddlewareResolverInterface $resolver) {}

    /**
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middlewares
     * @param callable(RequestContextInterface): mixed $destination
     */
    public function process(RequestContextInterface $context, array $middlewares, callable $destination): mixed
    {
        $next = $destination;

        foreach (array_reverse($middlewares) as $definition) {
            $middleware = is_string($definition) ? $this->resolver->resolve($definition) : $definition;

            if (!$middleware instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException('Middleware must implement MiddlewareInterface.');
            }

            $previous = $next;
            $next = static fn (RequestContextInterface $request): mixed =>
                $middleware->process($request, $previous);
        }

        return $next($context);
    }
}
