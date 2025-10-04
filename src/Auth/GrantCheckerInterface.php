<?php

namespace Mithridatem\Routing\Auth;

interface GrantCheckerInterface
{
    public function isGranted(array $requiredGrants): bool;
}