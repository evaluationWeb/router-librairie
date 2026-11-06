# Architecture

## Couche coeur

- `src/Router.php` expose l'API publique et orchestre matching, invocation et middlewares, sans implementer leurs details.
- `src/Controller/ControllerInvoker.php` verifie les grants, resout le callable et les arguments puis invoque le handler.
- `src/Path/PathNormalizer.php` centralise la normalisation des chemins et le retrait du prefixe applicatif (base path).
- `src/RouteInterface.php` definit le contrat structurel commun (chemin, verbes HTTP, handler, grants, middleware, matching).
- `src/Route.php` est l'implementation historique du contrat, avec chemins dynamiques et roles requis.
- `src/RouteCollection.php` stocke les routes et renvoie un `RouteMatch`.
- `src/RouteMatch.php` encapsule la route selectionnee et les parametres extraits.
- `src/Group/RouteGroup.php` collecte des routes et leur configuration commune.
- `src/Group/GroupedRoute.php` applique ces paramètres via un décorateur `RouteInterface`, sans modifier `Route`.

## Abstractions principales

- `Context\RequestContextInterface` fournit chemin, verbe HTTP et jeton Bearer.
- `Controller\ControllerResolverInterface` permet d'injecter un conteneur.
- `Auth\GrantCheckerInterface` laisse l'application gerer ses droits.
- `RouteInterface` permet des definitions alternatives de routes, sans imposer de classes de routes Web/API.

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

## Contrat des routes et compatibilite

`RouteInterface` regroupe les accesseurs de route et les operations `matches()` / `matchesPath()`.
Ces deux operations restent dans le contrat parce que `RouteCollection` s'appuie sur elles pour
rechercher une route et calculer les methodes autorisees pour les reponses 405.

`Route` implemente desormais `RouteInterface` sans modifier son constructeur,
ses factories ou son comportement. `Router::map()`, `RouteCollection::add()/remove()`
et `RouteMatch::__construct()` acceptent des `RouteInterface`.
`RouteCollection::all()` retourne une liste de `RouteInterface`, et
`RouteMatch::getRoute()` declare le type de retour `RouteInterface`.
Pour les routes creees avec `Route`, l'objet retourne reste bien une instance de `Route`.

### Limite de compatibilite de typage

La modification du type de retour declare de `RouteMatch::getRoute()` (de `Route`
vers `RouteInterface`) peut necessiter une adaptation de code consommateur dont les
signatures ou analyses statiques exigent explicitement `Route`. Les sous-classes
qui surchargent `getRoute(): Route` restent compatibles par covariance, mais un
code qui ne peut manipuler que `Route` devra verifier le type de l'objet ou accepter
le contrat `RouteInterface`. Un code qui construit une `Route` et la recupere du
match obtient toujours cet objet concret.

### Hors perimetre

Pas d'ajout de `WebRoute`, `ApiRoute` ou `SecurityPolicy`. JWT, CSRF et les
regles metier d'autorisation restent pris en charge par les middlewares et
le `GrantCheckerInterface` fournis par l'application.

## Groupes facultatifs

Les groupes déclarés par `Router::group()` ou `#[RouteGroup]` appliquent un préfixe,
les grants hérités si une route n'en fournit pas, et les middlewares du groupe avant
ceux de la route. Voir [les groupes de routes](route-groups.md).
