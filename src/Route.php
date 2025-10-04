<?php

namespace Mithridatem\Routing;

use Closure;
use Mithridatem\Routing\Handler\ControllerReference;

class Route
{
    private string $regex;
    private array $parameterNames = [];
    private Closure|ControllerReference $handler;

    public function __construct(
        private array $methods,
        private string $path,
        ControllerReference|callable $handler,
        private array $grants = [],
        private array $defaultParameters = []
    ) {
        $this->methods = array_values(array_unique(array_map('strtoupper', $methods)));
        $this->path = $this->normalizePath($path);
        $this->handler = $handler instanceof ControllerReference ? $handler : Closure::fromCallable($handler);
        $this->compilePattern();
    }

    public static function get(string $path, ControllerReference|callable $handler, array $grants = []): self
    {
        return new self(['GET'], $path, $handler, $grants);
    }

    public static function post(string $path, ControllerReference|callable $handler, array $grants = []): self
    {
        return new self(['POST'], $path, $handler, $grants);
    }

    public static function any(array $methods, string $path, ControllerReference|callable $handler, array $grants = []): self
    {
        return new self($methods, $path, $handler, $grants);
    }

    public static function controller(
        string $method,
        string $path,
        string $controllerClass,
        string $controllerMethod,
        array $grants = []
    ): self {
        return new self([$method], $path, new ControllerReference($controllerClass, $controllerMethod), $grants);
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): Closure|ControllerReference
    {
        return $this->handler;
    }

    public function getGrants(): array
    {
        return $this->grants;
    }

    public function getDefaultParameters(): array
    {
        return $this->defaultParameters;
    }

    public function matches(string $method, string $path): ?array
    {
        if (!in_array(strtoupper($method), $this->methods, true)) {
            return null;
        }

        $path = $this->normalizePath($path);

        if (!preg_match($this->regex, $path, $matches)) {
            return null;
        }

        $parameters = [];
        foreach ($this->parameterNames as $name) {
            if (array_key_exists($name, $matches)) {
                $parameters[$name] = $matches[$name];
            }
        }

        return array_replace($this->defaultParameters, $parameters);
    }

    private function compilePattern(): void
    {
        $this->parameterNames = [];

        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/', function (array $matches) {
            $this->parameterNames[] = $matches[1];

            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $this->path);

        $pattern = str_replace('/*', '/.*', $pattern ?? $this->path);
        $this->regex = '#^' . $pattern . '$#';
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        $clean = '/' . ltrim($path, '/');
        return rtrim($clean, '/') ?: '/';
    }
}