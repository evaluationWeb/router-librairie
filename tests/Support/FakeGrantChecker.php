<?php

namespace Mithridatem\Routing\Tests\Support;

use Mithridatem\Routing\Auth\GrantCheckerInterface;

class FakeGrantChecker implements GrantCheckerInterface
{
    public function __construct(private readonly array $grants)
    {
    }

    public function isGranted(array $requiredGrants): bool
    {
        if ($requiredGrants === []) {
            return true;
        }

        foreach ($this->grants as $grant) {
            if (in_array($grant, $requiredGrants, true)) {
                return true;
            }
        }

        return false;
    }
}