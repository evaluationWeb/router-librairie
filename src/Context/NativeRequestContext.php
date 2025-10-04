<?php

namespace Mithridatem\Routing\Context;

class NativeRequestContext implements RequestContextInterface
{
    public function __construct(
        private readonly string $path,
        private readonly string $method,
        private readonly ?string $bearerToken = null
    ) {
    }

    public static function fromGlobals(): self
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $url = parse_url($requestUri);
        $path = $url['path'] ?? '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        return new self($path, $method, self::extractBearer());
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getBearerToken(): ?string
    {
        return $this->bearerToken;
    }

    private static function extractBearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if ($header === null) {
            return null;
        }

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return null;
    }
}