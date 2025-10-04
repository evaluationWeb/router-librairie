<?php

namespace Mithridatem\Routing\Context;

interface RequestContextInterface
{
    public function getPath(): string;

    public function getMethod(): string;

    public function getBearerToken(): ?string;
}