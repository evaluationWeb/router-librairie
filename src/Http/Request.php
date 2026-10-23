<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Http;

use Mithridatem\Routing\Context\NativeRequestContext;
use Mithridatem\Routing\Exception\RouterException;

final class Request implements RequestInterface
{
    /** @var array<string, string> */
    private readonly array $headers;
    private readonly string $method;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $path,
        string $method = 'GET',
        private readonly array $query = [],
        private readonly array $post = [],
        array $headers = [],
        private readonly string $content = ''
    ) {
        $this->method = strtoupper($method);
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }
        $this->headers = $normalized;
    }

    public static function fromGlobals(): self
    {
        $context = NativeRequestContext::fromGlobals();
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($name, 5))] = $value;
            } elseif ($name === 'CONTENT_TYPE' || $name === 'CONTENT_LENGTH') {
                $headers[str_replace('_', '-', $name)] = $value;
            }
        }

        $content = file_get_contents('php://input');
        if ($content === false) {
            throw new RouterException('Unable to read the HTTP request body.');
        }

        return new self(
            $context->getPath(),
            $context->getMethod(),
            $_GET,
            $_POST,
            $headers,
            $content
        );
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
        $header = $this->header('Authorization');

        return $header !== null && stripos($header, 'Bearer ') === 0
            ? trim(substr($header, 7))
            : null;
    }

    public function query(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->query) ? $this->query[$name] : $default;
    }

    public function post(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->post) ? $this->post[$name] : $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
