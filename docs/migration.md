# Migration vers Mithridatem Router

Ce guide accompagne la transition depuis un routeur applicatif maison vers la bibliotheque `Mithridatem\Routing`.

## 1. Installation

- Ajoutez la dependance via Composer : `composer require mithridatem/router`
- Executez `composer dump-autoload` pour mettre a jour l'autoload PSR-4.

## 2. Enregistrement des routes

Ancien style :

```php
$router->addRoute(new Route('/users', 'GET', 'User', 'index'));
```

Nouvelle approche :

```php
use Mithridatem\Routing\Route;

$router->map(Route::controller(
    'GET',
    '/users',
    App\Controller\UserController::class,
    'index',
    ['ROLE_ADMIN']
));
```

## 3. Gestion des autorisations

- Implantez `Mithridatem\Routing\Auth\GrantCheckerInterface` pour brancher votre logique d'ACL.
- `Mithridatem\Routing\Auth\AllowAllGrantChecker` peut servir de valeur par defaut.

## 4. Resolution des controleurs

- Le resolver par defaut instancie la classe via `new`.
- Integrez votre conteneur de dependances en implementant `ControllerResolverInterface`.

## 5. Tests

- Ajoutez des tests unitaires pour chaque route critique (grants, parametres dynamiques).
- Utilisez les doubles dans `tests/Support` pour simuler le contexte HTTP.

## 6. Publication

- Versionnez vos modifications, taggez `v1.0.0`.
- Enregistrez le package sur Packagist pour le rendre installable.
