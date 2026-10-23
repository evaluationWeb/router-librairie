# Fichiers envoyés par formulaire

`Request::fromGlobals()` convertit `$_FILES` en objets `UploadedFile`, sans modifier
la superglobale. Les métadonnées PHP restent disponibles :

| Métadonnée PHP | Objet |
| --- | --- |
| `tmp_name` | `$file->getTmpName()` |
| `name` | `$file->getName()` |
| `size` | `$file->getSize()` (octets) |
| `error` | `$file->getError()` (constante `UPLOAD_ERR_*`) |

La clé native est `error`, au singulier. `name` conserve le nom reçu par PHP,
sans renommage supplémentaire par la librairie. `type` et `full_path`, fournis
par le client, ne sont pas utilisés par cette API.

## Fichier simple et contrôleur avec Attribute

Le formulaire doit utiliser `method="post"` et `enctype="multipart/form-data"` :

```html
<form method="post" action="/books/42/cover" enctype="multipart/form-data">
    <input type="file" name="cover">
    <button type="submit">Envoyer</button>
</form>
```

```php
use Mithridatem\Routing\Attribute\Route;
use Mithridatem\Routing\Http\FileRequestInterface;

#[Route(path: '/books/{id}/cover', method: 'POST')]
public function upload(string $id, FileRequestInterface $request): string
{
    $file = $request->file('cover');
    if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
        return 'Aucun fichier sélectionné.';
    }
    if (!$file->isValid()) {
        return 'Erreur de transfert : ' . $file->getError();
    }

    // Valider ici la taille et le contenu selon les règles du projet.
    // Appeler ensuite le service de stockage, par exemple :
    // $this->storage->saveCover($id, $file);
    return 'Fichier reçu : ' . $file->getSize() . ' octets.';
}
```

`file('cover')` retourne `null` seulement si le champ est absent. Si PHP fournit
une entrée avec `UPLOAD_ERR_NO_FILE`, l'objet est conservé avec ce code d'erreur.
Tous les codes d'erreur restent consultables, même en cas de transfert échoué.

`isValid()` exige un code `UPLOAD_ERR_OK`, un vrai fichier d'upload reconnu par
`is_uploaded_file()` et un objet qui n'a pas déjà déplacé le fichier. Cela ne
valide ni le type de contenu ni les règles métier.

## Plusieurs fichiers et champs imbriqués

```html
<input type="file" name="attachments[]" multiple>
<input type="file" name="documents[identity][front]">
```

```php
foreach ($request->files('attachments') as $index => $file) {
    if ($file->isValid()) {
        // Traiter le fichier avec le service de stockage.
    }
}

$front = $request->files('documents')['identity']['front'] ?? null;
$allFiles = $request->files();
```

La normalisation préserve les clés numériques, associatives et tous les niveaux
d'imbrication. Aucune entrée n'est supprimée en fonction de son code d'erreur.

- `files()` retourne l'arbre complet, indexé par noms de champs.
- `files('attachments')` retourne le sous-arbre de ce champ, ou `[]` s'il est absent.
- Pour un champ simple, `files('cover')` retourne `[$file]`.
- `file()` sur un champ multiple ou imbriqué lève `InvalidArgumentException` :
  utilisez `files()` pour ne pas confondre un groupe de fichiers avec un champ absent.

## Déplacement et durée de vie

```php
use Mithridatem\Routing\Exception\FileUploadException;

// Après validation : répertoire existant, configuré hors de la racine publique.
$destination = $uploadDirectory . '/' . bin2hex(random_bytes(16)) . '.bin';

try {
    $file->moveTo($destination);
} catch (FileUploadException $exception) {
    // Journaliser l'erreur et adapter la réponse de l'application.
}
```

`moveTo()` utilise uniquement `move_uploaded_file()` : il ne déplace pas un
fichier local ordinaire avec `rename()` ou `copy()`. Il ne crée pas le répertoire
de destination. Une destination vide, contenant un octet nul, inaccessible,
un transfert invalide ou une seconde tentative après succès provoque une
`FileUploadException` (sous-classe de `RouterException`). Après un échec de
destination, un upload encore valide peut être déplacé vers un autre chemin.

Comme la fonction PHP native, `moveTo()` peut remplacer un fichier existant à
destination. Choisissez un nom de stockage généré par l'application. Le nom
d'origine est une donnée client, à ne pas concaténer directement au répertoire.

Après succès, `isValid()` retourne `false`. Les quatre getters gardent les valeurs
d'origine : `getTmpName()` n'est pas remplacé par la destination et ne désigne
plus un fichier existant. L'application conserve elle-même le chemin final.
Sans déplacement, PHP supprime normalement le fichier temporaire à la fin de la requête.

## Construction explicite et tests

Le constructeur existant de `Request` conserve ses arguments et reçoit seulement
un dernier argument facultatif `files`, qui contient des objets déjà normalisés :

```php
use Mithridatem\Routing\Http\Request;
use Mithridatem\Routing\Http\UploadedFile;
use Mithridatem\Routing\Http\UploadedFileNormalizer;

$file = new UploadedFile(
    tmpName: '/tmp/php123',
    name: 'cover.png',
    size: 123,
    error: UPLOAD_ERR_OK
);
$request = new Request('/books/42/cover', 'POST', files: ['cover' => $file]);

// Pour adapter un tableau PHP existant sans le modifier :
$files = UploadedFileNormalizer::fromPhpFiles($_FILES);
$request = new Request('/books/42/cover', 'POST', files: $files);
```

Les métadonnées ainsi construites sont testables, mais un fichier local fabriqué
en test ne devient pas un upload HTTP : `isValid()` retourne `false` et `moveTo()`
le refuse. Le test `UploadHttpTest` utilise un vrai POST multipart vers le serveur
PHP pour vérifier le déplacement natif, ses erreurs et la compatibilité avec `$_FILES`.
Des métadonnées PHP incomplètes, mal typées ou des tableaux parallèles incohérents
provoquent une `InvalidArgumentException` explicite.

## Compatibilité

`FileRequestInterface` étend `RequestInterface`, qui reste inchangée.
`Request` implémente cette nouvelle interface et reste compatible avec les actions
existantes typées `RequestInterface` ou `Request`, et les anciens arguments du constructeur.
Les implémentations personnalisées de `RequestInterface` n'ont aucune méthode à ajouter.
Une action qui exige `FileRequestInterface` doit recevoir un contexte qui implémente
cette interface ; les anciens contextes minimaux restent utilisables pour les autres actions.

`$_FILES`, `is_uploaded_file()` et `move_uploaded_file()` restent utilisables
directement. Construire ou dispatcher une `Request` ne modifie pas `$_FILES` et
ne déplace aucun fichier. Si l'application déplace elle-même un upload, son objet
ne le considère ensuite plus valide ; évitez de déplacer le même fichier deux fois.

Les limites PHP `upload_max_filesize`, `post_max_size` et `max_file_uploads`
s'appliquent toujours. En particulier, dépasser `post_max_size` peut laisser
`$_POST` et `$_FILES` vides : l'objet ne peut pas reconstruire les données écartées
par PHP. Cette API couvre les uploads multipart analysés par PHP ; elle ne parse
pas automatiquement les corps multipart PUT/PATCH.

## Exemple exécutable

```bash
composer install
php -S localhost:8080 examples/upload.php
```

Ouvrez `http://localhost:8080/upload`, ou utilisez un autre terminal :

```bash
curl -F 'cover=@/path/to/file.txt' http://localhost:8080/upload
```

L'exemple limite la taille à 2 Mio, conserve le nom d'origine pour l'affichage,
et stocke sous un nom aléatoire dans le répertoire temporaire
`mithridatem-router-example-uploads`. Configurez un stockage durable hors de
la racine publique pour une application réelle.
