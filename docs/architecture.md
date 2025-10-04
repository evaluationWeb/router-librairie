# Architecture

## Couche coeur

- `src/Router.php` orchestre la recherche de route, le controle des autorisations et l'invocation du handler.
- `src/Route.php` decrit une route HTTP avec chemins dynamiques et roles requis.
- `src/RouteCollection.php` stocke les routes et renvoie un `RouteMatch`.
- `src/RouteMatch.php` encapsule la route selectionnee et les parametres extraits.

## Abstractions principales

- `Context\RequestContextInterface` fournit chemin, verbe HTTP et jeton Bearer.
- `Controller\ControllerResolverInterface` permet d'injecter un conteneur.
- `Auth\GrantCheckerInterface` laisse l'application gerer ses droits.

## Exceptions

- `Exception\RouterException` : base commune.
- `Exception\RouteNotFoundException` : lancee si aucune route ne matche.
- `Exception\UnauthorizedException` : lancee si l'utilisateur n'a pas les droits.

## Extension

- Ajoutez vos propres `GrantCheckerInterface` et `ControllerResolverInterface` dans un namespace applicatif.
- Composez les routes dans des fichiers de configuration ou des providers dedicaces.
