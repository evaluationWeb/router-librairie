<?php

namespace Mithridatem\Routing\Auth;

class AllowAllGrantChecker implements GrantCheckerInterface
{
    public function isGranted(array $requiredGrants): bool
    {
        return true;
    }
}