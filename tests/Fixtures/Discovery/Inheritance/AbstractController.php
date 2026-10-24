<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Fixtures\Discovery\Inheritance;

use Mithridatem\Routing\Attribute\Route;

abstract class AbstractController
{
    protected function render(string $template): string
    {
        return 'view:' . $template;
    }

    #[Route(path: '/inherited-base', method: 'GET')]
    public function inherited(): string
    {
        return 'from-base';
    }
}
