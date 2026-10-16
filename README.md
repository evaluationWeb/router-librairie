# Mithridatem Router

Mithridatem Router est une bibliotheque PHP oriente PSR-4 qui fournit un mecanisme de routage HTTP minimaliste, testable et independant de tout framework.

## Caracteristiques clefs

- API fluide pour declarer des routes GET, POST ou multi-methodes avec segments dynamiques `{id}` et wildcards `/*`.
- Resolution de controlleurs parametres via references (`Route::controller`) compatible avec un conteneur de dependances.
- Controle d'acces branchable grace a l'interface `GrantCheckerInterface` (implementations `AllowAllGrantChecker`, `ArrayGrantChecker` ; le routeur utilise `ArrayGrantChecker([])` par defaut).
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

## Routage par Attributes et decouverte automatique

Depuis PHP 8.1, vous pouvez declarer les routes directement sur les methodes
des controleurs avec l'Attribute `#[Route]`. La decouverte automatique permet
de charger tous les controleurs du repertoire `src/Controller/` (y compris
ses sous-repertoires) sans les enregistrer individuellement.

### 1. Configurer l'autoload PSR-4 de votre application

Dans le `composer.json` du **projet utilisant la librairie** :

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
```

Apres une modification de l'autoload :

```bash
composer dump-autoload
```

Arborescence d'exemple :

```text
mon-projet/
├── composer.json
├── public/
│   └── index.php
└── src/
    └── Controller/
        ├── HomeController.php
        └── Admin/
            └── DashboardController.php
```

### 2. Declarer les routes dans les controleurs

Fichier `src/Controller/HomeController.php` :

```php
<?php

namespace App\Controller;

use Mithridatem\Routing\Attribute\Route;

class HomeController
{
    #[Route(path: '/', method: 'GET')]
    public function index(): string
    {
        return 'Accueil';
    }

    #[Route(path: '/books/{id}', method: 'GET')]
    public function show(string $id): string
    {
        return "Livre #$id";
    }
}
```

Fichier `src/Controller/Admin/DashboardController.php` :

```php
<?php

namespace App\Controller\Admin;

use Mithridatem\Routing\Attribute\Route;

class DashboardController
{
    #[Route(path: '/admin/dashboard', method: 'GET', grants: ['ROLE_ADMIN'])]
    public function index(): string
    {
        return 'Tableau de bord';
    }
}
```

L'Attribute accepte aussi plusieurs methodes HTTP :
`#[Route(path: '/endpoint', method: ['GET', 'POST'])]`.

### 3. Charger automatiquement les routes

Dans `public/index.php` :

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Mithridatem\Routing\Loader\AttributeRouteLoader;
use Mithridatem\Routing\Router;

$router = new Router();

(new AttributeRouteLoader())->loadDirectory(
    router: $router,
    directory: __DIR__ . '/../src/Controller',
    namespace: 'App\\Controller'
);

echo $router->dispatch();
```

Le dossier **doit correspondre au namespace PSR-4**. Par exemple,
`src/Controller/Admin/DashboardController.php` doit declarer
`namespace App\Controller\Admin;`.

Le chargement utilise l'autoload Composer et n'inclut pas directement les
fichiers trouves. Les classes non autoloadables sont ignorees.

### 4. Autorisations et compatibilite

Les routes contenant `grants` sont refusees par defaut en l'absence de
droits configures. Pour cet exemple :

```php
use Mithridatem\Routing\Auth\ArrayGrantChecker;

$router->setGrantChecker(new ArrayGrantChecker(['ROLE_ADMIN']));
```

Vous pouvez toujours melanger decouverte automatique et declaration manuelle :

```php
use Mithridatem\Routing\Route;

$router->map(Route::get('/health', fn () => 'OK'));
```

Pour un controleur ayant des dependances dans son constructeur, configurez
un `ControllerResolverInterface` adapte. La decouverte ne fournit pas
d'injection de dependances.

Consultez egalement le guide detaille : [Decouverte automatique des controleurs](docs/controller-discovery.md).

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
