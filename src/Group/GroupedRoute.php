<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Group;

use Closure;
use Mithridatem\Routing\Handler\ControllerReference;
use Mithridatem\Routing\Path\PathNormalizer;
use Mithridatem\Routing\RouteInterface;

/**
 * Decorates any RouteInterface with a static prefix and inherited configuration.
 * Route-specific non-empty grants replace group grants, never merge with them.
 */
final class GroupedRoute implements RouteInterface
{
    private string $path;
    private string $prefix;

    public function __construct(
        private RouteInterface $route,
        string $prefix = '/',
        private array $groupGrants = [],
        private array $groupMiddleware = []
    ) {
        $normalizer = new PathNormalizer();
        $this->prefix = $normalizer->normalize($prefix);
        $this->path = $normalizer->normalize(
            ($this->prefix === '/' ? '' : $this->prefix) . $route->getPath()
        );
    }

    public function getPath(): string { return $this->path; }
    public function getMethods(): array { return $this->route->getMethods(); }
    public function getHandler(): Closure|ControllerReference { return $this->route->getHandler(); }
    public function getGrants(): array
    {
        return $this->route->getGrants() !== [] ? $this->route->getGrants() : $this->groupGrants;
    }
    public function getMiddleware(): array
    {
        return [...$this->groupMiddleware, ...$this->route->getMiddleware()];
    }
    public function getDefaultParameters(): array { return $this->route->getDefaultParameters(); }

    public function matches(string $method, string $path): ?array
    {
        $relative = $this->relativePath($path);
        return $relative === null ? null : $this->route->matches($method, $relative);
    }

    public function matchesPath(string $path): bool
    {
        $relative = $this->relativePath($path);
        return $relative !== null && $this->route->matchesPath($relative);
    }

    private function relativePath(string $path): ?string
    {
        $path = (new PathNormalizer())->normalize($path);
        if ($this->prefix === '/') {
            return $path;
        }
        if ($path === $this->prefix) {
            return '/';
        }
        if (!str_starts_with($path, $this->prefix . '/')) {
            return null;
        }
        return substr($path, strlen($this->prefix));
    }
}
