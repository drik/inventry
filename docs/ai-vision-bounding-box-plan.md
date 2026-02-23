# Plan : Bounding Box pour AI Vision

## Contexte
L'app mobile envoie une photo à l'API AI Vision (Gemini/GPT-4o) pour identifier un objet d'inventaire. Actuellement, l'IA analyse toute l'image sans savoir quel objet cibler. On veut permettre à l'app mobile d'envoyer des coordonnées de bounding box pour qu'un rectangle rouge soit dessiné sur l'image avant envoi à l'IA, guidant ainsi l'analyse sur l'objet ciblé.

**Approche :** Dessiner un rectangle rouge (pas de crop) pour préserver le contexte visuel + augmenter le prompt pour indiquer à l'IA de se concentrer sur l'objet dans le rectangle.

## Fichiers à modifier

1. `app/Services/AiVisionService.php` — coeur des changements
2. `app/Http/Controllers/Api/AiVisionController.php` — validation + passage des paramètres

## Changements détaillés

### 1. `AiVisionService.php`

**a) Modifier `analyzePhoto()` — ajouter paramètre `boundingBox`**
```php
public function analyzePhoto(
    ...
    ?array $boundingBox = null,  // AJOUT
): array {
```
Et passer `$boundingBox` à `prepareImage()` :
```php
$capturedBase64 = $this->prepareImage($imagePath, $boundingBox);
```

**b) Modifier `verifyAssetIdentity()` — même ajout**
```php
public function verifyAssetIdentity(
    ...
    ?array $boundingBox = null,  // AJOUT
): array {
```
Et passer à `prepareImage()`.

**c) Modifier `prepareImage()` — accepter et appliquer le bounding box**
```php
protected function prepareImage(string $absolutePath, ?array $boundingBox = null): string
{
    $image = Image::read($absolutePath);
    $image->scaleDown(1024, 1024);

    if ($boundingBox) {
        $this->drawBoundingBox($image, $boundingBox);
    }

    return base64_encode((string) $image->toJpeg(85));
}
```

**d) Ajouter `drawBoundingBox()` — nouvelle méthode**
- Convertit les coordonnées % (0-1) en pixels
- Clamp aux limites de l'image
- Dessine un rectangle rouge 3px sans remplissage via `$image->drawRectangle()`
- API Intervention Image v3 confirmée : `RectangleFactory::size()`, `::border('ff0000', 3)`

```php
protected function drawBoundingBox($image, array $boundingBox): void
{
    $imgWidth = $image->width();
    $imgHeight = $image->height();

    $x = (int) round($boundingBox['x'] * $imgWidth);
    $y = (int) round($boundingBox['y'] * $imgHeight);
    $width = (int) round($boundingBox['width'] * $imgWidth);
    $height = (int) round($boundingBox['height'] * $imgHeight);

    // Clamp to image boundaries
    $x = max(0, min($x, $imgWidth - 1));
    $y = max(0, min($y, $imgHeight - 1));
    $width = min($width, $imgWidth - $x);
    $height = min($height, $imgHeight - $y);

    $image->drawRectangle($x, $y, function ($rectangle) use ($width, $height) {
        $rectangle->size($width, $height);
        $rectangle->border('ff0000', 3);
    });
}
```

**e) Modifier `getSystemPrompt()` — ajouter instruction bounding box**
```php
protected function getSystemPrompt(bool $hasBoundingBox = false): string
```
Quand `$hasBoundingBox = true`, ajouter la règle :
> "L'objet cible est mis en évidence par un rectangle rouge sur la photo capturée. Concentre ton analyse UNIQUEMENT sur l'objet à l'intérieur de ce rectangle rouge."

Mettre à jour les 2 appels dans `analyzePhoto()` et `verifyAssetIdentity()`.

### 2. `AiVisionController.php`

**a) `identify()` — ajouter validation bbox + passer au service**
```php
// Validation (ajout aux règles existantes)
'bbox_x' => 'sometimes|numeric|min:0|max:1',
'bbox_y' => 'sometimes|numeric|min:0|max:1',
'bbox_width' => 'sometimes|numeric|min:0.01|max:1',
'bbox_height' => 'sometimes|numeric|min:0.01|max:1',
```
Construire `$boundingBox` array si les 4 champs sont présents, passer à `analyzePhoto()`.

**b) `verify()` — même ajout**

## API Mobile (contrat)

| Champ | Type | Requis | Description |
|---|---|---|---|
| `bbox_x` | float | Non | X coin supérieur-gauche (0.0 à 1.0) |
| `bbox_y` | float | Non | Y coin supérieur-gauche (0.0 à 1.0) |
| `bbox_width` | float | Non | Largeur (0.01 à 1.0) |
| `bbox_height` | float | Non | Hauteur (0.01 à 1.0) |

Les 4 doivent être envoyés ensemble ou pas du tout. 100% rétrocompatible.

## Ce qui ne change PAS
- Providers (Gemini/OpenAI) — reçoivent du base64 déjà annoté
- DTOs — la forme de la réponse IA reste identique
- Routes — paramètres optionnels sur les endpoints existants
- Base de données — pas de migration (l'image stockée reste l'originale sans annotation)
- `prepareImageFromStorage()` — utilisé uniquement pour les images de référence, pas de bbox

## Vérification
1. Tester l'endpoint `ai-identify` **sans** bbox → comportement identique à avant
2. Tester **avec** bbox → vérifier que l'image envoyée à l'IA contient un rectangle rouge
3. Vérifier que l'image stockée dans `storage/app/public/ai-captures/` ne contient **pas** le rectangle
4. Tester l'endpoint `ai-verify` avec bbox
5. Tester avec des coordonnées limites (bbox au bord de l'image)
