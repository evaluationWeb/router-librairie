# Architecture

## Couche coeur

- `src/Router.php` expose l'API publique et orchestre matching, invocation et middlewares, sans implementer leurs details.
- `src/Controller/ControllerInvoker.php` verifie les grants, resout le callable et les arguments puis invoque le handler.
- `src/Path/PathNormalizer.php` centralise la normalisation des chemins et le retrait du prefixe applicatif (base path).
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

Après le matching, les middlewares globaux puis ceux de la route sont executes.
Le dernier maillon utilise `ControllerInvoker` : il verifie les grants via
`GrantCheckerInterface`, resout le callable via `ControllerResolverInterface`,
puis les arguments via `ArgumentResolverInterface`, avant d'invoquer le handler.
`DefaultArgumentResolver` conserve les parametres de route et ajoute la requete
aux parametres qui la demandent par leur type. Le resultat est retourne tel quel.
Un middleware peut interrompre la chaine avant le controle des grants, comme auparavant.

Les setters publics de `Router` restent disponibles et sont pris en compte lors
de chaque appel a `dispatch()`. Aucune adaptation des projets consommateurs n'est requise.

Voir [le guide HTTP](http-request.md) pour le cycle de vie et les limites.
