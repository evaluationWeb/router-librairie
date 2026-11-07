<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Exception\MethodNotAllowedException;
use Mithridatem\Routing\Exception\RouteNotFoundException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Http\Request;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;

// Isolated HTTP fixture: intentionally no authentication, so protected routes
// must always fail closed. Never deploy this file in a public web root.
$router = new Router();
$router->setGrantChecker(new ArrayGrantChecker([]));
$router->map(Route::get('/health', static fn (): string => 'ok'));
$router->map(Route::get('/admin/{id}', static fn (string $id): string => 'secret-' . $id, ['ROLE_ADMIN']));
$router->map(Route::post('/submit', static fn (): string => 'submitted', ['ROLE_USER']));

try {
    $result = $router->dispatch(Request::fromGlobals());
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo $result;
} catch (UnauthorizedException) {
    http_response_code(403);
    echo 'forbidden';
} catch (MethodNotAllowedException) {
    http_response_code(405);
    echo 'method-not-allowed';
} catch (RouteNotFoundException) {
    http_response_code(404);
    echo 'not-found';
}
