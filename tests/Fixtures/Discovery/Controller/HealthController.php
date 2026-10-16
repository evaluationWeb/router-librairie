<?php

namespace Mithridatem\Routing\Tests\Fixtures\Discovery\Controller;

use Mithridatem\Routing\Attribute\Route;

final class HealthController
{
    #[Route(path: '/health', method: 'GET')]
    public function index(): string
    {
        return 'healthy';
    }
}
