# Mithridatem Router

Bibliotheque PHP oriente PSR-4 qui expose un systeme de routage leger, extensible et independant du framework. Elle est issue de la factorisation du routeur utilise dans l'application Librairie et peut etre distribuee via Composer.

## Installation

```bash
composer require mithridatem/router
```

> En developpement local dans ce depot, pensez a executer `composer dump-autoload` apres chaque modification de namespace.

## Fonctionnalites

- Declaration fluide de routes HTTP (`GET`, `POST`, multi-methodes) avec parametres dynamiques `{id}`.
- Verification des autorisations configurable via l'interface `GrantCheckerInterface`.
- Resolution de controlleurs personnalisable (`ControllerResolverInterface`) compatible conteneur de dependances.
- Contexte de requete injecte (`RequestContextInterface`) pour decoupler des superglobales PHP.
- Exceptions dediees (`RouteNotFoundException`, `UnauthorizedException`) pour simplifier la gestion des erreurs.

## Exemple rapide

```php
use Mithridatem\Routing\Router;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Handler\ControllerReference;

$router = new Router();
$router->map(Route::get('/', function () {
    echo 'Hello world';
}));

$router->map(
    Route::controller('GET', '/books/{id}', App\Controller\BookController::class, 'showBook')
);

$router->dispatch();
```

### Avec controlleur dedie

```php
use Mithridatem\Routing\Router;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Handler\ControllerReference;

$router = new Router();
$router->map(Route::controller(
    'GET',
    '/admin/dashboard',
    App\Controller\AdminController::class,
    'dashboard',
    ['ROLE_ADMIN']
));

$router->dispatch();
```

## Configuration avancee

- `Router::setBasePath('/mon-app')` pour supporter une application dans un sous-repertoire.
- `Router::setGrantChecker(new MonGrantChecker())` pour integrer votre logique d'authentification.
- `Router::setControllerResolver(new ContainerAwareResolver($container))` si vos controlleurs sont geres par un conteneur DI.
- `Router::setDefaultContext($context)` pour injecter un contexte HTTP alternatif (tests, workers).

## Tests

```bash
composer install
composer test
```

Les tests utilisent PHPUnit (configuration `phpunit.xml.dist`). Des doubles (`tests/Support`) sont fournis pour simuler le contexte HTTP et le resolveur de controlleurs.

## Contribuer

1. Fork & clone
2. Creez une branche feature
3. Ajoutez vos tests (`composer test`)
4. Soumettez une pull request

Consultez `CONTRIBUTING.md` et `SECURITY.md` pour les bonnes pratiques.
