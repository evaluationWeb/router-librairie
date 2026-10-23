<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Http;

use Mithridatem\Routing\Context\NativeRequestContext;
use Mithridatem\Routing\Exception\RouterException;

final class Request implements FileRequestInterface
{
    /** @var array<string, string> */
    private readonly array $headers;
    private readonly string $method;
    /** @var array<string|int, UploadedFile|array> */
    private readonly array $files;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $headers
     * @param array<string|int, UploadedFile|array> $files Normalized file tree.
     */
    public function __construct(
        private readonly string $path,
        string $method = 'GET',
        private readonly array $query = [],
        private readonly array $post = [],
        array $headers = [],
        private readonly string $content = '',
        array $files = []
    ) {
        self::validateFiles($files);
        $this->files = $files;
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
            $content,
            UploadedFileNormalizer::fromPhpFiles($_FILES)
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

    public function file(string $name): ?UploadedFile
    {
        $file = $this->files[$name] ?? null;
        if (is_array($file)) {
            throw new \InvalidArgumentException('Use files() for a multiple or nested file field: ' . $name);
        }

        return $file;
    }

    public function files(?string $name = null): array
    {
        if ($name === null) {
            return $this->files;
        }

        $files = $this->files[$name] ?? [];

        return $files instanceof UploadedFile ? [$files] : $files;
    }

    private static function validateFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_array($file)) {
                self::validateFiles($file);
            } elseif (!$file instanceof UploadedFile) {
                throw new \InvalidArgumentException('Request files must contain UploadedFile objects or nested arrays.');
            }
        }
    }
}
