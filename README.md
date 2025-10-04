# Mithridatem Router

Mithridatem Router est une bibliotheque PHP oriente PSR-4 qui fournit un mecanisme de routage HTTP minimaliste, testable et independant de tout framework.

## Caracteristiques clefs

- API fluide pour declarer des routes GET, POST ou multi-methodes avec segments dynamiques `{id}` et wildcards `/*`.
- Resolution de controlleurs parametres via references (`Route::controller`) compatible avec un conteneur de dependances.
- Controle d'acces branchable grace a l'interface `GrantCheckerInterface` (implementations par defaut `AllowAllGrantChecker`, `ArrayGrantChecker`).
- Contexte de requete abstrait (`RequestContextInterface`) pour isoler les superglobales PHP et faciliter les tests.
- Base path configurable (`Router::setBasePath`) pour les applications hebergees derriere un prefixe.
- Exceptions explicites (`RouteNotFoundException`, `UnauthorizedException`, `RouterException`) pour une gestion d'erreur claire.

## Installation

```bash
composer require mithridatem/router
```

En developpement local sur ce depot, pensez a executer `composer dump-autoload` apres toute modification de namespace.

## Premiers pas

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;

$router = new Router();

// Route simple
$router->map(Route::get('/', fn () => 'Hello world'));

// Route avec parametre dynamique
$router->map(Route::get('/books/{id}', function (string $id) {
    return "Livre #$id";
}));

echo $router->dispatch();
```

Par defaut `dispatch()` detecte la requete courante via `NativeRequestContext::fromGlobals()`. Pour des tests unitaires, injectez votre propre contexte :

```php
use Mithridatem\Routing\Context\RequestContextInterface;

final class FakeContext implements RequestContextInterface
{
    public function __construct(private string $path, private string $method) {}

    public function getPath(): string { return $this->path; }
    public function getMethod(): string { return $this->method; }
    public function getBearerToken(): ?string { return null; }
}

$router->dispatch(new FakeContext('/books/42', 'GET'));
```

## Controlleurs et resolution

Lorsque vos handlers resident dans des classes, utilisez `Route::controller()` et laissez le routeur resoudre la methode correspondante :

```php
$router->mapController(
    'GET',
    '/admin/dashboard',
    App\Controller\AdminController::class,
    'dashboard'
);
```

Par defaut, `DefaultControllerResolver` instancie la classe sans dependances. Pour deleguer la resolution a un conteneur, fournissez votre propre implementation de `ControllerResolverInterface` :

```php
$router->setControllerResolver(new ContainerAwareResolver($container));
```

## Gestion des autorisations

Associez des droits a une route en fournissant un tableau de "grants" :

```php
use Mithridatem\Routing\Auth\ArrayGrantChecker;

$router->setGrantChecker(new ArrayGrantChecker(['ROLE_ADMIN']));

$router->map(Route::get('/admin', fn () => 'Admin panel', ['ROLE_ADMIN']));
```

Si l'utilisateur courant ne possede aucun des droits requis, une `UnauthorizedException` est levee. Branchez votre propre verificateur via `GrantCheckerInterface` pour relier vos mecanismes d'authentification.

## Base path et sous-repertoires

Hebergez votre application derriere un prefixe sans re-ecrire vos routes :

```php
$router->setBasePath('/mon-app');
```

Toutes les correspondances s'effectuent alors relativement a ce prefixe.

## Gestion des erreurs

- Route introuvable : `RouteNotFoundException`.
- Acces refuse : `UnauthorizedException`.
- Erreur de resolution : `RouterException`.

Capturez ces exceptions et adaptez la reponse HTTP de votre application.

## Tests et exemples

```bash
composer install
composer test
```

Un exemple minimal est disponible dans `examples/basic.php`. Des doubles de tests se trouvent dans `tests/Support` pour simuler le contexte HTTP et les controlleurs.

## Aller plus loin

- Documentation d'architecture : `docs/architecture.md`.
- Guide de migration : `docs/migration.md`.
- Processus de contribution : `CONTRIBUTING.md` et `SECURITY.md`.
