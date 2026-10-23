<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mithridatem\Routing\Attribute\Route;
use Mithridatem\Routing\Http\RequestInterface;
use Mithridatem\Routing\Loader\AttributeRouteLoader;
use Mithridatem\Routing\Router;

// php -S localhost:8080 examples/request-controller.php
// curl -X POST http://localhost:8080/books/42 -d 'title=PHP'

final class RequestExampleBookController
{
    #[Route(path: '/books/{id}', method: 'POST')]
    public function update(RequestInterface $request, string $id): string
    {
        $title = $request->post('title');
        if (!is_string($title) || trim($title) === '') {
            http_response_code(400);
            return 'Le titre est obligatoire.';
        }

        return sprintf(
            'Livre %s : %s',
            htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars(trim($title), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}

$router = new Router();
(new AttributeRouteLoader())->loadController($router, RequestExampleBookController::class);

echo $router->dispatch();
