<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Http;

use Mithridatem\Routing\Context\RequestContextInterface;

interface RequestInterface extends RequestContextInterface
{
    public function query(string $name, mixed $default = null): mixed;

    public function post(string $name, mixed $default = null): mixed;

    public function header(string $name, ?string $default = null): ?string;

    public function getContent(): string;
}
