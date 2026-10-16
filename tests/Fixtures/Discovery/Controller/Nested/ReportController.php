<?php

namespace Mithridatem\Routing\Tests\Fixtures\Discovery\Controller\Nested;

use Mithridatem\Routing\Attribute\Route;

final class ReportController
{
    #[Route(path: '/reports/{id}', method: 'GET')]
    public function show(string $id): string
    {
        return 'report:' . $id;
    }
}
