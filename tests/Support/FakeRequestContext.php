<?php

namespace Mithridatem\Routing\Tests\Support;

use Mithridatem\Routing\Context\RequestContextInterface;

class FakeRequestContext implements RequestContextInterface
{
    public function __construct(
        private readonly string $path,
        private readonly string $method = 'GET',
        private readonly ?string $bearer = null
    ) {
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
        return $this->bearer;
    }
}