<?php

namespace Mithridatem\Routing\Tests\Support;

use Mithridatem\Routing\Attribute\Route;

class AttributeDemoController
{
    #[Route(path: '/products', method: 'GET')]
    #[Route(path: '/catalog', method: ['GET', 'POST'])]
    public function index(): string
    {
        return 'products';
    }

    #[Route(path: '/products/{id}', method: 'GET', grants: ['ROLE_ADMIN'])]
    public function show(string $id): string
    {
        return $id;
    }

    public function withoutRoute(): string
    {
        return 'hidden';
    }
}
