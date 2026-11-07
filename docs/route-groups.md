# Groupes de routes

Les groupes sont **optionnels** et ne modifient ni `Router::map()`, ni `RouteInterface`,
ni les attributs de méthode existants.

## Déclaration PHP

```php
use Mithridatem\Routing\Group\RouteGroup;
use Mithridatem\Routing\Route;

$router->group('/api', function (RouteGroup $group): void {
    $group->grants(['ROLE_USER']);
    $group->middleware(JwtMiddleware::class); // Middleware de l'application
    $group->map(Route::get('/profile', [$controller, 'profile']));
    $group->map(Route::get('/admin', [$controller, 'admin'], ['ROLE_ADMIN']));
});
```

Le groupe ajoute `/api` à chaque chemin. `mapController()` est également disponible
dans le groupe. Toutes les routes sont enregistrées à la fin de la callback.

## Attributs sur un contrôleur

```php
use Mithridatem\Routing\Attribute\Route;
use Mithridatem\Routing\Attribute\RouteGroup;

#[RouteGroup(prefix: '/web', grants: ['ROLE_USER'], middleware: [CsrfMiddleware::class])]
final class ProfileController
{
    #[Route(path: '/profile', method: 'GET')]
    public function profile(): string { return 'profile'; }

    #[Route(path: '/admin', method: 'GET', grants: ['ROLE_ADMIN'])]
    public function admin(): string { return 'admin'; }
}
```

`AttributeRouteLoader` applique l'attribut de classe à toutes les routes des méthodes.

## Héritage des grants

- Une route avec `grants: []` hérite des grants du groupe.
- Une route avec des grants non vides **remplace** les grants du groupe.
- Une liste de grants reste évaluée en **OU** par `ArrayGrantChecker` :
  au moins un rôle suffit.
- Les rôles cumulés (par ex. `ROLE_USER` et `ROLE_ADMIN`) sont affectés par
  l'application, sans hiérarchie ajoutée par la librairie.

**Sécurité :** `ROLE_USER` sur le groupe n'autorise jamais un utilisateur classique
sur une route précisant `ROLE_ADMIN`. Un tableau vide ne peut pas rendre une route
publique à l'intérieur d'un groupe protégé : placer les routes publiques hors de ce groupe.

## Risque de configuration des autorisations

> **Attention : l'héritage n'est pas cumulatif.** La déclaration de grants
> spécifiques sur une route **désactive les grants du groupe pour cette route**.
> Il s'agit du comportement prévu par l'API, et non d'un contournement HTTP
> démontré. Le risque apparaît si l'application suppose à tort que les
> restrictions de groupe restent obligatoires.

Exemple :

```php
use Mithridatem\Routing\Group\RouteGroup;
use Mithridatem\Routing\Route;

$router->group('/admin', function (RouteGroup $group) use ($controller): void {
    $group->grants(['ROLE_ADMIN']);

    // Hérite de ROLE_ADMIN : l'accès nécessite ROLE_ADMIN.
    $group->map(Route::get('/dashboard', [$controller, 'dashboard']));

    // Remplace ROLE_ADMIN par ROLE_USER : un ROLE_USER seul suffit.
    $group->map(Route::get('/reports', [$controller, 'reports'], ['ROLE_USER']));
});
```

| Route | Grants réellement vérifiés | Conséquence |
| --- | --- | --- |
| `GET /admin/dashboard` | `ROLE_ADMIN` | Accès réservé à `ROLE_ADMIN` |
| `GET /admin/reports` | `ROLE_USER` | `ROLE_ADMIN` du groupe **n'est pas exigé** |

**Bonnes pratiques :**

- Définir explicitement les grants de chaque route sensible et vérifier
  le résultat effectif lorsque la route est placée dans un groupe.
- Ne pas supposer que `['ROLE_ADMIN', 'ROLE_USER']` impose les deux rôles :
  `ArrayGrantChecker` applique un **OU**.
- Si une application exige une politique **ET** (par exemple :
  utilisateur authentifié **et** rôle administrateur), la configurer
  explicitement dans son contrôle d'accès ; ne pas utiliser une simple
  concaténation des tableaux de rôles pour la simuler.
- Ajouter des tests d'autorisation pour chaque route protégée, notamment
  lorsqu'une route surcharge les grants du groupe.

Cette règle s'applique aussi aux contrôleurs utilisant
`#[RouteGroup(grants: [...])]` et `#[Route(grants: [...])]`.

## Ordre des middlewares

`globaux → groupe → route → grants → contrôleur`, puis retour dans l'ordre inverse.
L'authentification JWT et la protection CSRF sont des implémentations de l'application,
pas des responsabilités du routeur.

## Limites intentionnelles

- Les groupes sont plats (pas de groupes imbriqués dans cette première version).
- La composition est obtenue par un décorateur `GroupedRoute` qui implémente
  `RouteInterface` ; les routes existantes restent inchangées.
- Les objets `RouteInterface` personnalisés peuvent être placés dans un groupe,
  à condition de respecter leur contrat de matching.

Voir aussi [architecture](architecture.md).
