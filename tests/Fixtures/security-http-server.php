<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Exception\MethodNotAllowedException;
use Mithridatem\Routing\Exception\RouteNotFoundException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Http\Request;
use Mithridatem\Routing\Http\FileRequestInterface;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;

// Isolated HTTP fixture: intentionally no authentication, so protected routes
// must always fail closed. Never deploy this file in a public web root.
$router = new Router();
$router->setGrantChecker(new ArrayGrantChecker([]));
$router->map(Route::get('/health', static fn (): string => 'ok'));
$router->map(Route::get('/admin/{id}', static fn (string $id): string => 'secret-' . $id, ['ROLE_ADMIN']));
$router->map(Route::post('/submit', static fn (): string => 'submitted', ['ROLE_USER']));

// Test-only upload endpoint. A trusted, random temporary destination is chosen
// by the fixture, NEVER from the HTTP filename or any client-supplied path.
$router->map(Route::post('/upload-check', static function (FileRequestInterface $request): string {
    $file = $request->file('cover');
    if ($file === null) {
        return json_encode(['present' => false], JSON_THROW_ON_ERROR);
    }
    $before = $file->isValid();
    $name = $file->getName();
    $destination = tempnam(sys_get_temp_dir(), 'router-sec-');
    if ($destination === false) {
        throw new RuntimeException('Cannot allocate temporary destination.');
    }
    // tempnam creates a file: move_uploaded_file() will replace it.
    try {
        $file->moveTo($destination);
        $result = [
            'present' => true,
            'originalName' => $name,
            'validBeforeMove' => $before,
            'validAfterMove' => $file->isValid(),
            'contentPreserved' => file_get_contents($destination) === 'security-upload-payload',
            'secondMoveRejected' => false,
        ];
        try {
            $file->moveTo($destination . '-again');
        } catch (\Mithridatem\Routing\Exception\FileUploadException) {
            $result['secondMoveRejected'] = true;
        }
        return json_encode($result, JSON_THROW_ON_ERROR);
    } finally {
        @unlink($destination);
    }
}));

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
