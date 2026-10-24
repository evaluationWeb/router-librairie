<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Fixtures\Discovery\Inheritance;

use Mithridatem\Routing\Attribute\Route;

final class DependentController
{
    public function __construct(private string $message)
    {
    }

    #[Route(path: '/dependent', method: 'GET')]
    public function index(): string
    {
        return $this->message;
    }
}
