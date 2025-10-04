<?php

namespace Mithridatem\Routing\Auth;

class ArrayGrantChecker implements GrantCheckerInterface
{
    public function __construct(private readonly array $grants)
    {
    }

    public function isGranted(array $requiredGrants): bool
    {
        if ($requiredGrants === []) {
            return true;
        }

        foreach ($this->grants as $userGrant) {
            if (in_array($userGrant, $requiredGrants, true)) {
                return true;
            }
        }

        return false;
    }
}