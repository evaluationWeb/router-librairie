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

Par defaut `dispatch()` detecte la requete courante via `Http\Request::fromGlobals()`. Les anciens contextes restent acceptes. Pour des tests unitaires, injectez votre propre contexte :

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

## Requete HTTP et injection dans les actions

L'objet `Http\Request` donne acces aux parametres d'URL, aux champs de formulaire,
aux en-tetes et au corps brut. Il implemente `RequestInterface`, qui etend le
contrat `RequestContextInterface` existant.

```php
use Mithridatem\Routing\Http\RequestInterface;

$router->map(Route::post('/books/{id}', function (string $id, RequestInterface $request): string {
    $title = $request->post('title');
    if (!is_string($title) || trim($title) === '') {
        return 'Le titre est obligatoire.';
    }

    // Appeler ici le service metier.
    return 'Livre modifie.';
}));

echo $router->dispatch();
```

La requete est injectee par son type, quelle que soit sa position dans la signature.
Les parametres de route restent transmis par nom. Ce mecanisme fonctionne aussi
avec les methodes annotees `#[Route]` et les resolvers de controleurs existants.
`query('page', '1')` lit les donnees d'URL ; `post('title')` lit celles du formulaire.
Le JSON se lit via `getContent()` puis se decode explicitement.

Un ancien contexte minimal reste compatible avec les actions existantes. Une action
demandant `RequestInterface` necessite un contexte compatible, sinon une
`RouterException` est levee.

Voir le [guide des requetes HTTP](docs/http-request.md), les exemples executables
[avec closures](examples/request.php) et [avec Attributes](examples/request-controller.php).

## Fichiers envoyes par formulaire

`Request` implemente aussi `FileRequestInterface`, qui etend `RequestInterface`
sans la modifier. Les implementations personnalisees existantes restent compatibles.

```php
use Mithridatem\Routing\Http\FileRequestInterface;

$router->map(Route::post('/upload', function (FileRequestInterface $request): string {
    $file = $request->file('cover');
    if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
        return 'Aucun fichier selectionne.';
    }
    if (!$file->isValid()) {
        return 'Erreur de transfert : ' . $file->getError();
    }

    // Metadonnees conservees : tmp_name, name, size et error.
    // Valider le contenu puis utiliser le service de stockage.
    return 'Fichier recu : ' . $file->getSize() . ' octets.';
}));
```

`files('attachments')` gere les fichiers multiples et les champs imbriques.
`UploadedFile::moveTo($destination)` encapsule `move_uploaded_file()` ; le nom
d'origine et le chemin temporaire restent consultables apres le deplacement.
La superglobale `$_FILES` reste intacte et utilisable avec l'ancienne methode.

Voir le [guide des uploads](docs/file-uploads.md) et
[l'exemple avec formulaire](examples/upload.php).

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
Les classes abstraites (par exemple `AbstractController`) et les autres classes
non instanciables sont egalement ignorees lors de la decouverte du repertoire.
Les controleurs concrets peuvent donc heriter d'une base abstraite dans ce meme
dossier. Leurs methodes publiques heritees portant `#[Route]` restent chargees.
L'enregistrement explicite d'une classe non instanciable via `loadController()`
ou `loadControllers()` continue de lever une exception.

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

### 5. Injection de dependances dans un controleur

La decouverte automatique **enregistre les routes**, mais elle ne construit
pas les controleurs avec leurs dependances. Le resolver par defaut appelle
`new Controller()` ; pour injecter des services, il faut fournir un
`ControllerResolverInterface` personnalise.

Cet exemple utilise un registre d'instances, sans dependre d'un framework
ou d'un conteneur externe.

**Service :** `src/Service/ProductService.php`

```php
<?php

namespace App\Service;

final class ProductService
{
    public function findName(string $id): string
    {
        return 'Produit #' . $id;
    }
}
```

**Controleur :** `src/Controller/ProductController.php`

```php
<?php

namespace App\Controller;

use App\Service\ProductService;
use Mithridatem\Routing\Attribute\Route;

final class ProductController
{
    public function __construct(private ProductService $products)
    {
    }

    #[Route(path: '/products/{id}', method: 'GET')]
    public function show(string $id): string
    {
        return $this->products->findName($id);
    }
}
```

**Resolver :** `src/Routing/InstanceControllerResolver.php`

```php
<?php

namespace App\Routing;

use Mithridatem\Routing\Controller\ControllerResolverInterface;
use Mithridatem\Routing\Handler\ControllerReference;
use Mithridatem\Routing\Exception\RouterException;

final class InstanceControllerResolver implements ControllerResolverInterface
{
    /** @param array<class-string, object> $controllers */
    public function __construct(private array $controllers)
    {
    }

    public function resolve(ControllerReference $controller): callable
    {
        $class = $controller->getClassName();
        $method = $controller->getMethodName();
        $instance = $this->controllers[$class] ?? null;

        if ($instance === null) {
            throw new RouterException("Controleur non enregistre : $class");
        }

        if (!is_callable([$instance, $method])) {
            throw new RouterException("Methode non accessible : $class::$method");
        }

        return [$instance, $method];
    }
}
```

**Point d'entree :** `public/index.php`

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Controller\ProductController;
use App\Routing\InstanceControllerResolver;
use App\Service\ProductService;
use Mithridatem\Routing\Loader\AttributeRouteLoader;
use Mithridatem\Routing\Router;

$products = new ProductService();
$productController = new ProductController($products);

$router = new Router();
$router->setControllerResolver(new InstanceControllerResolver([
    ProductController::class => $productController,
]));

(new AttributeRouteLoader())->loadDirectory(
    router: $router,
    directory: __DIR__ . '/../src/Controller',
    namespace: 'App\\Controller'
);

echo $router->dispatch();
```

La route `GET /products/42` renvoie `Produit #42`. La declaration
`#[Route]` est toujours decouverte automatiquement ; seule la creation
de l'instance du controleur est explicite. Les autres controleurs decouverts
doivent aussi etre enregistres dans ce resolver, sous peine d'erreur lors
du dispatch. Pour une application plus importante, remplacez le registre
par un resolver deleguant a un conteneur PSR-11.

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
