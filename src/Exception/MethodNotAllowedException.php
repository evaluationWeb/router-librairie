<?php

namespace Mithridatem\Routing\Exception;

class MethodNotAllowedException extends RouterException
{
    /** @param string[] $allowedMethods */
    public function __construct(private readonly array $allowedMethods)
    {
        parent::__construct('Method not allowed. Allowed: ' . implode(', ', $allowedMethods), 405);
    }

    /** @return string[] */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
