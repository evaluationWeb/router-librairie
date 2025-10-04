<?php

namespace Mithridatem\Routing\Controller;

use Mithridatem\Routing\Handler\ControllerReference;

interface ControllerResolverInterface
{
    public function resolve(ControllerReference $controller): callable;
}