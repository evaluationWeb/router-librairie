<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mithridatem\Routing\Http\RequestInterface;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;

// php -S localhost:8080 examples/request.php
// curl 'http://localhost:8080/books?page=2'
// curl -X POST 'http://localhost:8080/books/42?preview=1' -d 'title=PHP'

$router = new Router();

$router->map(Route::get('/books', function (RequestInterface $request): string {
    $page = $request->query('page', '1');
    if (!is_string($page) || !ctype_digit($page) || (int) $page < 1) {
        http_response_code(400);
        return 'La page doit être un entier positif.';
    }

    return 'Liste des livres, page ' . $page;
}));

$router->map(Route::post('/books/{id}', function (string $id, RequestInterface $request): string {
    $title = $request->post('title');
    if (!is_string($title) || trim($title) === '') {
        http_response_code(400);
        return 'Le titre est obligatoire.';
    }

    // L'objet Request ne fait ni validation métier ni échappement HTML.
    return sprintf(
        'Livre %s : %s (aperçu : %s)',
        htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        htmlspecialchars(trim($title), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $request->query('preview') === '1' ? 'oui' : 'non'
    );
}));

echo $router->dispatch();
