<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Mithridatem\Routing\Exception\FileUploadException;
use Mithridatem\Routing\Http\FileRequestInterface;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;

$before = $_FILES;
$destination = getenv('ROUTER_UPLOAD_TEST_DESTINATION');
$router = new Router();
$router->map(Route::post('/upload', function (FileRequestInterface $request) use ($before, $destination): array {
    $file = $request->file('cover');
    $tmpName = $file->getTmpName();
    $result = [
        'globalsUnchanged' => $before === $_FILES,
        'tmpNamePreserved' => $tmpName === $_FILES['cover']['tmp_name'],
        'name' => $file->getName(),
        'size' => $file->getSize(),
        'error' => $file->getError(),
        'valid' => $file->isValid(),
        'title' => $request->post('title'),
        'preview' => $request->query('preview'),
        'attachments' => array_map(static fn ($f) => [$f->getName(), $f->isValid()], $request->files('attachments')),
        'nested' => $request->files('documents')['identity']['front']->getName(),
        'empty' => [$request->file('optional')->getError(), $request->file('optional')->isValid()],
    ];

    foreach (['', "invalid\0path", $destination . '/missing/file'] as $invalidDestination) {
        try {
            $file->moveTo($invalidDestination);
            $result['failedDestinations'][] = false;
        } catch (FileUploadException $exception) {
            $result['failedDestinations'][] = $file->isValid();
        }
    }

    $file->moveTo($destination);
    $result['content'] = file_get_contents($destination);
    $result['validAfterMove'] = $file->isValid();
    $result['metadataAfterMove'] = [$file->getTmpName() === $tmpName, $file->getName(), $file->getSize(), $file->getError()];
    try {
        $file->moveTo($destination . '-again');
        $result['secondMoveRejected'] = false;
    } catch (FileUploadException $exception) {
        $result['secondMoveRejected'] = true;
    }

    return $result;
}));

// The original superglobal API and native move remain usable without injection.
$router->map(Route::post('/legacy', function () use ($destination): array {
    return [
        'name' => $_FILES['cover']['name'],
        'size' => $_FILES['cover']['size'],
        'error' => $_FILES['cover']['error'],
        'moved' => move_uploaded_file($_FILES['cover']['tmp_name'], $destination),
    ];
}));

$router->map(Route::post('/inspect-error', static function (FileRequestInterface $request): array {
    $file = $request->file('cover');

    return [$file->getName(), $file->getTmpName(), $file->getSize(), $file->getError(), $file->isValid()];
}));

header('Content-Type: application/json');
echo json_encode($router->dispatch(), JSON_THROW_ON_ERROR);
