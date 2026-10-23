<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Support;

use Mithridatem\Routing\Attribute\Route;
use Mithridatem\Routing\Http\RequestInterface;

final class RequestDemoController
{
    public function __construct(private string $prefix)
    {
    }

    #[Route(path: '/books/{id}', method: 'POST')]
    public function update(RequestInterface $request, string $id): string
    {
        return $this->prefix . ' ' . $id . ': ' . $request->post('title');
    }
}
