# Représenter la requête HTTP

`Http\Request` implémente `Http\RequestInterface`, qui étend le contrat existant
`Context\RequestContextInterface`. Un même objet représente une requête GET,
POST ou toute autre méthode HTTP.

## Construction et accès aux données

```php
use Mithridatem\Routing\Http\Request;

$request = Request::fromGlobals();

$request->getMethod();                  // GET, POST…
$request->getPath();                    // /books, sans la query string
$request->query('page', '1');            // $_GET
$request->post('title');                // $_POST
$request->header('Content-Type');       // nom insensible à la casse
$request->getBearerToken();             // Authorization: Bearer …
$request->getContent();                 // corps brut, relisible
```

La factory lit les superglobales et `php://input` une fois à la construction.
L'objet conserve un instantané de ces données. Les en-têtes proviennent des
entrées `HTTP_*`, `CONTENT_TYPE` et `CONTENT_LENGTH` de `$_SERVER`.
Le serveur doit transmettre l'en-tête Authorization à PHP pour qu'il soit disponible.

`query()` et `post()` ne fusionnent jamais leurs sources : un POST peut aussi
avoir une query string. La valeur par défaut est utilisée seulement si la clé
est absente ; une valeur explicitement `null` reste `null`.
Les valeurs peuvent être des chaînes ou des tableaux : vérifiez leur type avant usage.
Aucune conversion, validation métier ou protection HTML n'est automatique.

`post()` concerne les formulaires analysés par PHP. Les fichiers sont accessibles
via `FileRequestInterface`, implémentée par `Request` : voir le
[guide des uploads](file-uploads.md). Le JSON, les corps PUT/PATCH et les cookies
ne sont pas analysés automatiquement.
Pour traiter du JSON, utilisez explicitement :

```php
$data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
// Vérifier ensuite la structure et les valeurs attendues.
```

Pour les tests, construisez une requête sans toucher aux superglobales :

```php
$request = new Request(
    path: '/books/42',
    method: 'POST',
    query: ['preview' => '1'],
    post: ['title' => 'PHP'],
    headers: ['Authorization' => 'Bearer token'],
    content: ''
);

$result = $router->dispatch($request);
```

Le constructeur attend le chemin seul ; la query string se fournit séparément.
La méthode HTTP est normalisée en majuscules.

## Injection dans une action ou une closure

```php
use Mithridatem\Routing\Attribute\Route;
use Mithridatem\Routing\Http\RequestInterface;

#[Route(path: '/books/{id}', method: 'POST')]
public function update(string $id, RequestInterface $request): string
{
    $title = $request->post('title');
    if (!is_string($title) || trim($title) === '') {
        return 'Le titre est obligatoire.';
    }

    // Appeler ici le service métier.
    return 'Livre modifié.';
}
```

La requête peut apparaître avant ou après les paramètres de route. Le résolveur
reconnaît un type simple `RequestInterface`, `Request`, ou un sous-type compatible.
Les types nullable sont acceptés et reçoivent aussi la requête courante.
Les unions et intersections ne déclenchent pas l'injection automatique.
Les paramètres de requête variadiques ou passés par référence sont refusés.
Un nom de placeholder identique au nom d'un paramètre injecté est également refusé
pour éviter de masquer une donnée de route.

Les paramètres dynamiques restent associés par nom. Les valeurs par défaut,
les paramètres variadiques ordinaires et la coercition scalaire PHP existante
sont conservés. Un paramètre obligatoire introuvable conserve l'erreur native PHP.
Les données GET/POST ne sont jamais injectées automatiquement dans les scalaires.

## Compatibilité et cycle de vie

- `dispatch()` sans contexte construit désormais une `Request` complète.
- `dispatch($context)` et `setDefaultContext($context)` gardent leur signature.
- `NativeRequestContext`, `RequestContextInterface` et les anciens doubles de tests
  restent utilisables avec les handlers existants.
- Une action demandant une requête complète avec un contexte minimal reçoit une
  `RouterException`, même si son paramètre est nullable ou possède une valeur par défaut.
  Le routeur ne complète pas ce contexte avec des superglobales.
- L'objet injecté est exactement le contexte fourni. Le retrait du base path
  sert uniquement au matching ; `getPath()` conserve le chemin original.
- Une requête transmise à `dispatch()` remplace le contexte par défaut pour cet
  appel uniquement. Dans un worker persistant, créez une requête par traitement.

Le `ControllerResolverInterface` continue à construire les contrôleurs et leurs
services, notamment via un conteneur. Le nouveau `ArgumentResolverInterface`
prépare les arguments des actions après le contrôle des autorisations.
La requête n'a pas besoin d'être enregistrée dans le conteneur pour cette injection.
Ce mécanisme n'injecte pas de services supplémentaires dans les actions.

Un résolveur personnalisé peut être branché avec :

```php
$router->setArgumentResolver($myArgumentResolver);
```

Sa méthode `resolve(callable $handler, array $routeParameters,
RequestContextInterface $context): array` doit retourner les arguments par nom.
Il remplace le résolveur par défaut ; pour conserver l'injection standard,
il peut déléguer à `DefaultArgumentResolver` puis enrichir le résultat.

## Exemples exécutables

Après `composer install` :

```bash
php -S localhost:8080 examples/request.php
```

Depuis un autre terminal :

```bash
curl 'http://localhost:8080/books?page=2'
curl -X POST 'http://localhost:8080/books/42?preview=1' -d 'title=PHP'
curl -X POST 'http://localhost:8080/books/42' -d 'title[]='
```

Le dernier appel renvoie une erreur 400 car le titre est un tableau.
Pour essayer la variante avec Attributes, arrêtez le premier serveur puis lancez :

```bash
php -S localhost:8080 examples/request-controller.php
```

```bash
curl -X POST 'http://localhost:8080/books/42' -d 'title=PHP'
```
