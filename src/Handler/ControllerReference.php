<?php

namespace Mithridatem\Routing\Handler;

class ControllerReference
{
    public function __construct(
        private readonly string $className,
        private readonly string $methodName
    ) {
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }
}