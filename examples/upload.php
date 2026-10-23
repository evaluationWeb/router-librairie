<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mithridatem\Routing\Exception\FileUploadException;
use Mithridatem\Routing\Http\FileRequestInterface;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;

// php -S localhost:8080 examples/upload.php
// Open http://localhost:8080/upload or use:
// curl -F 'cover=@/path/to/file.txt' http://localhost:8080/upload

$router = new Router();
$router->map(Route::get('/upload', static fn (): string => <<<'HTML'
<!doctype html>
<html lang="fr">
<meta charset="utf-8">
<title>Envoyer un fichier</title>
<h1>Envoyer un fichier</h1>
<form method="post" action="/upload" enctype="multipart/form-data">
    <label>Fichier (2 Mio maximum) <input type="file" name="cover" required></label>
    <button type="submit">Envoyer</button>
</form>
</html>
HTML));

$router->map(Route::post('/upload', static function (FileRequestInterface $request): string {
    try {
        $file = $request->file('cover');
    } catch (InvalidArgumentException $exception) {
        http_response_code(400);
        return 'Le champ cover attend un seul fichier.';
    }
    if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
        http_response_code(400);
        return 'Sélectionnez un fichier.';
    }
    if (!$file->isValid()) {
        http_response_code(400);
        return 'Le transfert a échoué, code : ' . $file->getError();
    }
    if ($file->getSize() > 2 * 1024 * 1024) {
        http_response_code(413);
        return 'Le fichier dépasse la limite de 2 Mio.';
    }

    // Demo storage outside the served application. Configure durable storage
    // outside the document root in a real project.
    $directory = sys_get_temp_dir() . '/mithridatem-router-example-uploads';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        http_response_code(500);
        return 'Le stockage est indisponible.';
    }

    try {
        // The original client name is metadata, never a storage path.
        $file->moveTo($directory . '/' . bin2hex(random_bytes(16)) . '.bin');
    } catch (FileUploadException $exception) {
        http_response_code(500);
        return 'Le fichier n’a pas pu être enregistré.';
    }

    return 'Fichier enregistré : ' . htmlspecialchars($file->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' (' . $file->getSize() . ' octets).';
}));

echo $router->dispatch();
