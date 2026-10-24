# Changelog

Toutes les modifications notables de **Mithridatem Router** sont documentées ici.

Le projet suit le [versionnement sémantique](https://semver.org/lang/fr/) et
s'inspire du format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).

## [Unreleased]

### Corrigé
- La découverte automatique des contrôleurs ignore les classes abstraites et les
  autres classes non instanciables, permettant de conserver `AbstractController`
  dans le même dossier que ses enfants concrets sans bloquer `loadDirectory()`.
- Tests de régression sur les routes héritées, l'absence d'instanciation pendant
  la découverte et la compatibilité des contrôleurs avec dépendances et des
  enregistrements explicites.

### Fichiers HTTP
- Ajout de `UploadedFile` avec conservation des métadonnées `tmp_name`, `name`, `size` et `error`, validation native de l'upload et déplacement via `moveTo()`.
- Nouvelle interface `FileRequestInterface` étendant `RequestInterface` sans changer son contrat ; accès aux champs simples, multiples et imbriqués via `file()` et `files()`.
- Normalisation de `$_FILES` sans modification de la superglobale et conservation des entrées en erreur, notamment `UPLOAD_ERR_NO_FILE`.
- Dernier argument facultatif `files` dans `Request`, tests unitaires et test HTTP multipart réel couvrant aussi l'ancienne méthode `move_uploaded_file($_FILES[...]['tmp_name'], ...)`.
- Guide `docs/file-uploads.md` et exemple exécutable `examples/upload.php`.

### Ajouté
- Objet `Http\Request` et `RequestInterface` : méthode, chemin, query string, formulaire, en-têtes insensibles à la casse, token Bearer et corps brut.
- Construction explicite pour les tests ou `Request::fromGlobals()` pour la requête courante.
- Injection de la requête par type dans les closures et actions de contrôleurs, y compris les routes déclarées par Attributes.
- `ArgumentResolverInterface`, implémentation par défaut et `Router::setArgumentResolver()`.
- Tests de compatibilité et exemples exécutables avec closures et Attributes ; guide `docs/http-request.md`.

### Compatibilité
- Les contrats existants, les resolvers de contrôleurs et les paramètres de route transmis par nom sont conservés.
- `dispatch()` sans contexte construit désormais une `Request` complète ; les contextes minimaux explicites restent acceptés pour les anciennes actions.
- Une injection demandée avec un contexte incompatible ou un nom de placeholder en conflit lève une `RouterException` explicite.

## [1.2.0] - 2026-10-16

### Ajouté
- Déclaration des routes sur les méthodes publiques des contrôleurs avec l'Attribute PHP répétable `#[Route(path: ..., method: ..., grants: ...)]`.
- Prise en charge d'une méthode HTTP ou d'un tableau de méthodes pour un même Attribute.
- `AttributeRouteLoader::loadController()` et `loadControllers()` : analyse des Attributes via la Reflection API et enregistrement dans le routeur existant.
- `ControllerDiscovery` : découverte récursive des classes de contrôleurs à partir d'un répertoire et d'un namespace PSR-4.
- `AttributeRouteLoader::loadDirectory()` : découverte et enregistrement automatiques des routes des contrôleurs.
- Tests PHPUnit couvrant les Attributes multiples, les méthodes HTTP, les paramètres, les autorisations et la découverte récursive.

### Documentation
- Guide `docs/controller-discovery.md`.
- Exemples complets dans le `README.md` : configuration PSR-4, contrôleurs annotés, découverte automatique et injection de dépendances via un `ControllerResolverInterface` personnalisé.

### Compatibilité
- Les déclarations existantes via `Route::get()`, `Route::post()`, `Router::map()` et `Router::mapController()` sont conservées.
- La découverte automatique nécessite des classes autoloadables avec une correspondance entre le répertoire et le namespace PSR-4. Elle n'instancie pas les services des contrôleurs et ne met pas en cache le résultat du scan.

## [1.1.0] - 2026-10-16

### Corrigé
- Échappement des caractères spéciaux des chemins littéraux lors de la compilation des expressions régulières.
- Correction de la gestion du `basePath` pour respecter les frontières des segments du chemin.
- Transmission des paramètres dynamiques aux handlers par **nom**, plutôt que par position.
- Refus des routes protégées lorsqu'aucun droit n'est configuré dans le vérificateur par défaut.
- Détection des doublons pour les routes déclarées avec un même chemin et des méthodes HTTP communes.

### Ajouté
- Distinction entre route absente (**404**) et méthode HTTP non autorisée (**405**) via `MethodNotAllowedException` et `getAllowedMethods()`.
- Tests de non-régression des comportements corrigés.

### Attention : changements de comportement
- Le routeur utilise désormais `ArrayGrantChecker([])` par défaut : une route comportant des `grants` n'est plus accessible sans vérificateur adapté.
- Les noms des paramètres des handlers doivent correspondre aux noms des placeholders de route.
- L'application consommatrice reste responsable de produire la réponse HTTP, y compris l'en-tête `Allow` lors d'une erreur 405.

## [1.0.1]

### Ajouté
- Workflow d'intégration continue GitHub Actions avec tests sur PHP 8.1, 8.2 et 8.3.
- Métadonnées Composer et documentation du dépôt enrichies.

### Corrigé
- Codes d'erreur explicites **404** (`RouteNotFoundException`) et **403** (`UnauthorizedException`).

## [1.0.0]

### Ajouté
- Première version de la librairie de routage HTTP `Mithridatem\\Routing`, distribuée via Composer.
- Déclaration de routes GET, POST et multi-méthodes, paramètres dynamiques et wildcards.
- Abstractions `GrantCheckerInterface`, `ControllerResolverInterface` et `RequestContextInterface`.
- `RouteCollection`, `RouteMatch`, résolveur de contrôleurs et prise en charge d'un préfixe `basePath`.
- Tests PHPUnit et documentation initiale.

[Unreleased]: https://github.com/evaluationWeb/router-librairie/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/evaluationWeb/router-librairie/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/evaluationWeb/router-librairie/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/evaluationWeb/router-librairie/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/evaluationWeb/router-librairie/releases/tag/v1.0.0
