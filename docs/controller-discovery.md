# Automatic controller discovery

Controllers can be discovered recursively using Composer PSR-4 autoloading.

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Mithridatem\Routing\Router;
use Mithridatem\Routing\Loader\AttributeRouteLoader;

$router = new Router();

(new AttributeRouteLoader())->loadDirectory(
    router: $router,
    directory: __DIR__ . '/../src/Controller',
    namespace: 'App\\Controller'
);

echo $router->dispatch();
```

Example controller:

```php
<?php

namespace App\Controller;

use Mithridatem\Routing\Attribute\Route;

final class HomeController
{
    #[Route(path: '/', method: 'GET')]
    public function index(): string
    {
        return 'Hello!';
    }
}
```

The directory and namespace must correspond to the application's Composer PSR-4 mapping (for example `"App\\": "src/"`). Run `composer dump-autoload` after changing the mapping.

The discovery component traverses PHP files recursively, derives class names from relative paths, and uses `class_exists()` to let Composer autoload classes. It does **not** execute arbitrary files with `require`. Files that do not map to an autoloadable class are ignored.

Existing `loadController()`, `loadControllers()`, `map()`, and `mapController()` APIs are unchanged. Controller instantiation still uses the configured controller resolver. Scanning happens each time `loadDirectory()` is called; there is no discovery cache in this version.
