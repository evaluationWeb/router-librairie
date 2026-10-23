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

## Requêtes et arguments des actions

`Http\RequestInterface` étend `RequestContextInterface` sans modifier ce dernier.
`Http\Request` capture les données HTTP ; `Router::dispatch()` l'utilise par défaut.
Un contexte explicite n'est jamais remplacé ou enrichi depuis les superglobales.

Après le matching et le contrôle des autorisations, `ControllerResolverInterface`
résout le callable du contrôleur, puis `ArgumentResolverInterface` prépare les
arguments nommés. `DefaultArgumentResolver` conserve les paramètres de route et
ajoute la requête aux paramètres qui la demandent par leur type.
Le routeur invoque ensuite le callable et retourne sa valeur sans la transformer.

Voir [le guide HTTP](http-request.md) pour le cycle de vie et les limites.
