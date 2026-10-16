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

    public function matchesPath(string $path): bool
    {
        return preg_match($this->regex, $this->normalizePath($path)) === 1;
    }

    private function compilePattern(): void
    {
        $this->parameterNames = [];
        $regex = '';
        $offset = 0;
        preg_match_all('/\\{([a-zA-Z_][a-zA-Z0-9_]*)\\}|\\/\\*/', $this->path, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($tokens as $token) {
            $literal = substr($this->path, $offset, $token[0][1] - $offset);
            $regex .= preg_quote($literal, '#');

            if ($token[0][0] === '/*') {
                $regex .= '/.*';
            } else {
                $name = $token[1][0];
                if (in_array($name, $this->parameterNames, true)) {
                    throw new \InvalidArgumentException('Duplicate route parameter: ' . $name);
                }
                $this->parameterNames[] = $name;
                $regex .= '(?P<' . $name . '>[^/]+)';
            }
            $offset = $token[0][1] + strlen($token[0][0]);
        }

        $regex .= preg_quote(substr($this->path, $offset), '#');
        $this->regex = '#^' . $regex . '$#D';
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