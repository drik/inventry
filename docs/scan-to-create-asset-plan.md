# Plan : Scan to Create Asset with AI

## Contexte

L'utilisateur veut permettre la création d'assets via l'IA, autant depuis le backoffice Filament que depuis l'API mobile. L'idée : prendre une ou **plusieurs photos** d'un produit (boîte, étiquette, numéro de série, différents angles...), l'IA analyse les images et combine les infos (nom, marque, modèle, numéro de série, SKU...) pour pré-remplir le formulaire de création d'asset.

**Placement du bouton "Créer avec IA"** : sur la page Liste des Assets ET la page Create Asset.
**Capture photo** : upload fichier (jusqu'à 5 images) via le FileUpload natif de Filament.
**API Mobile** : CRUD complet sur les assets + création via AI (multi-image avec rétrocompatibilité single).
**Contexte AI** : indépendant des sessions d'inventaire (pas de task_id requis).

---

## Phase 1 : DTO — `AiAssetExtractionResult`

**Nouveau fichier** : `app/DTOs/AiAssetExtractionResult.php`

Suivre le pattern de `app/DTOs/AiIdentificationResult.php` :

```php
readonly class AiAssetExtractionResult
{
    public function __construct(
        public ?string $suggestedName,
        public ?string $suggestedCategory,
        public ?string $suggestedBrand,
        public ?string $suggestedModel,
        public ?string $suggestedDescription,
        public ?string $serialNumber,
        public ?string $sku,
        public array   $detectedText,
        public float   $confidence,
    ) {}

    public static function fromAiResponse(array $data): self { ... }
    public function toArray(): array { ... }
}
```

---

## Phase 2 : AiVisionService — `extractAssetInfo()`

**Fichier à modifier** : `app/Services/AiVisionService.php`

### 2A. Nouvelle méthode `extractAssetInfo()` (multi-image)

```php
public function extractAssetInfo(
    array $imagePaths,            // ['path1', 'path2', ...] — jusqu'à 5 images
    Organization $organization,
    ?array $storagePaths = null,   // correspondance 1:1 avec imagePaths
): array
```

Flux :
1. Boucler sur `$imagePaths`, appeler `prepareImage()` pour chacune (resize 512px, JPEG 85%, base64)
2. Construire `$images = ['photo_1' => base64, 'photo_2' => base64, ...]` (ou `'captured'` si une seule image)
3. Charger les catégories et fabricants de l'org comme contexte
4. Construire le prompt d'extraction avec `buildExtractionPrompt($categories, $manufacturers, $imageCount)`
5. Sélectionner le provider via `getProvider()` existant
6. Appeler `$provider->analyze($images, ...)` avec try/catch + fallback (les providers supportent déjà les images multiples)
7. Parser la réponse en `AiAssetExtractionResult::fromAiResponse()`
8. Résoudre les noms suggérés en IDs via `resolveExtractionToIds()` (nouveau)
9. Logger via `logRecognition()` existant avec `use_case = 'asset_extraction'`, `task_id = null`
10. Retourner `['recognition_log_id', 'extraction' => DTO, 'resolved_ids' => [...], 'image_paths' => array]`

### 2B. Nouveau prompt d'extraction

Méthode `getAssetExtractionSystemPrompt()` :
```
Tu es un assistant spécialisé dans l'identification de produits et d'actifs physiques.
Tu analyses des photos d'objets pour en extraire les informations produit.
Réponds UNIQUEMENT en JSON valide.
Les scores de confiance vont de 0.0 à 1.0.
```

Méthode `buildExtractionPrompt($categories, $manufacturers, $imageCount)` :
```
[Si $imageCount > 1:]
Plusieurs photos du même objet sont fournies (différents angles, étiquettes, détails).
Combine les informations de toutes les photos pour une extraction la plus complète possible.

Les catégories d'actifs sont : {liste}
Les fabricants connus sont : {liste}

Analyse la/les photo(s) et retourne un JSON avec :
- "suggested_name" : nom descriptif
- "suggested_category" : parmi les catégories listées ou suggestion libre
- "suggested_brand" : parmi les fabricants connus ou nom détecté
- "suggested_model" : modèle si visible
- "serial_number" : numéro de série si visible
- "sku" : code SKU/référence si visible
- "detected_text" : tableau de tous les textes détectés
- "description" : description courte pour le champ notes
- "confidence" : score de confiance
```

### 2C. Résolution en IDs — `resolveExtractionToIds()`

```php
protected function resolveExtractionToIds(AiAssetExtractionResult $extraction, Organization $org): array
```

Stratégie de matching :
- **Catégorie** : match exact sur `name`, puis `LIKE %name%`, sinon null + suggestion
- **Fabricant** : idem, scope org + globals (via `Manufacturer::withoutGlobalScopes()`)
- **Modèle** : match par nom dans le scope du fabricant trouvé

Retourne : `['category_id', 'manufacturer_id', 'model_id', 'unmatched_suggestions' => [...]]`

### 2D. Stockage photo base64

Ajouter méthode `storeBase64Photo()` pour gérer les uploads depuis le web (base64 → fichier) :

```php
public function storeBase64Photo(Organization $org, string $base64Data): string
```

Décode le base64, stocke dans `ai-captures/{org_id}/{date}/`, retourne le storage path.

---

## Phase 3 : Filament — Bouton "Créer avec IA"

### 3A. Page ListAssets — Header Action (multi-image)

**Fichier** : `app/Filament/App/Resources/AssetResource/Pages/ListAssets.php`

Header action "Créer avec IA" utilisant le système modal natif de Filament :
- Icône `heroicon-o-sparkles`, couleur `info`
- Visible si `config('ai-vision.enabled')`
- Modal avec `FileUpload::make('photos')->multiple()->maxFiles(5)`
- Description modale : "Sélectionnez une ou plusieurs photos du produit (différents angles, étiquettes...)"
- L'action construit les chemins absolus depuis les storage paths
- Appelle `extractAssetInfo(imagePaths: $absolutePaths, storagePaths: $storagePaths)`
- Stocke le résultat en session avec `'image_paths'` (array), redirige vers CreateAsset

### 3B. Page CreateAsset — Pré-remplissage + AI inline (multi-image)

**Fichier** : `app/Filament/App/Resources/AssetResource/Pages/CreateAsset.php`

Modifications :
1. **Header action** "Remplir avec l'IA" — modal natif Filament avec `FileUpload::make('photos')->multiple()->maxFiles(5)`, remplit directement le form via `$this->form->fill()`
2. **Mount** — Vérifie si des données AI en session (`ai_asset_extraction`), si oui pré-remplit le formulaire
3. **`fillFormFromAiData()`** — Mappe les résultats AI vers les champs du formulaire (name, category_id, manufacturer_id, notes, tagValues). Stocke `$this->aiImagePaths` (array) avec rétrocompatibilité `image_path` → `[image_path]`
4. **`afterCreate()`** — Boucle sur `$this->aiImagePaths` pour créer un `AssetImage` par photo (première = `is_primary`, `sort_order` = index) + met à jour l'`AiRecognitionLog` avec `selected_asset_id`

**Note** : utilise `$this->data ?? []` au lieu de `$this->form->getState()` pour éviter la validation des champs required sur un formulaire vide.

Propriétés ajoutées :
```php
public ?string $aiRecognitionLogId = null;
public ?array $aiImagePaths = null;
```

### 3C. ~~Composant Blade — ai-photo-capture~~ (Abandonné)

Le composant Alpine.js custom (`ai-photo-capture.blade.php`) a été **remplacé par le système modal natif de Filament** (`->form([FileUpload])` + `->action()`). L'approche custom posait des conflits avec les handlers `wire:click`/`x-on:click` internes de Filament Actions.

Le fichier `resources/views/filament/forms/components/ai-photo-capture.blade.php` existe encore mais n'est plus utilisé.

---

## Phase 4 : API Mobile — Asset CRUD + AI

### 4A. Nouveau contrôleur

**Nouveau fichier** : `app/Http/Controllers/Api/AssetController.php`

7 endpoints :

| Méthode | Route | Description | Middleware |
|---------|-------|-------------|------------|
| GET | `/api/assets` | Liste paginée, recherche, filtres | `auth:sanctum` |
| GET | `/api/assets/{id}` | Détail avec relations | `auth:sanctum` |
| POST | `/api/assets` | Création manuelle | `auth:sanctum`, `plan.limit:max_assets` |
| PUT | `/api/assets/{id}` | Mise à jour | `auth:sanctum` |
| DELETE | `/api/assets/{id}` | Suppression douce | `auth:sanctum` |
| POST | `/api/assets/ai-extract` | Extraction AI (sans créer) | `auth:sanctum`, `throttle:ai-vision`, `plan.limit:max_ai_requests_daily` |
| POST | `/api/assets/ai-create` | Extraction AI + création | `auth:sanctum`, `throttle:ai-vision`, `plan.limit:max_ai_requests_daily`, `plan.limit:max_assets` |

#### `GET /api/assets` — Liste
- Scope par `organization_id`
- With: `category`, `location`, `manufacturer`, `assetModel`, `primaryImage`, `tagValues.tag`
- Recherche: `name`, `asset_code`, `tagValues.value`
- Filtres: `category_id`, `location_id`, `status`
- Pagination: 20 par page
- Tri: `created_at` DESC

#### `GET /api/assets/{id}` — Détail
- Même scope org
- With: toutes les relations (category, location, department, manufacturer, assetModel, supplier, images, tagValues.tag, currentAssignment.assignee)

#### `POST /api/assets` — Création
- Validation: name (required), category_id (required), location_id (required), manufacturer_id, model_id, status, purchase_date, purchase_cost, notes, tag_values (array), image (file)
- Auto-génère `asset_code` via `Asset::generateAssetCode()`
- Crée les `AssetTagValue` si fournis
- Crée `AssetImage` si image uploadée

#### `PUT /api/assets/{id}` — Mise à jour
- Même validation mais tout optionnel
- Sync des tag values

#### `DELETE /api/assets/{id}` — Suppression
- Soft delete via `$asset->delete()`

#### `POST /api/assets/ai-extract` — Extraction AI sans création (multi-image)
- Validation avec rétrocompatibilité :
  - `photos` : `required_without:photo|array|min:1|max:5` + `photos.*` : `image|mimes:jpeg,jpg,png|max:{maxSize}`
  - `photo` : `required_without:photos|image|mimes:jpeg,jpg,png|max:{maxSize}`
- Résolution : `$files = $request->file('photos') ?? [$request->file('photo')]`
- Vérifie quotas AI (daily + monthly)
- Boucle `storeCapturedPhoto()` pour chaque fichier
- Appelle `AiVisionService::extractAssetInfo(imagePaths: [...], storagePaths: [...])`
- Retourne: `recognition_log_id`, `extraction` (DTO), `resolved_ids`, `image_paths` (array), `usage`

#### `POST /api/assets/ai-create` — Extraction AI + Création (multi-image)
- Même validation multi-image avec rétrocompatibilité `photo`/`photos`
- `location_id` (required, AI ne peut pas le deviner) + overrides optionnels
- Boucle `storeCapturedPhoto()` puis appelle `extractAssetInfo()`
- Crée l'asset, puis boucle pour créer un `AssetImage` par photo (première = `is_primary`, `sort_order` = index)
- Met à jour `AiRecognitionLog` avec `selected_asset_id` et `selected_action = 'created'`
- Retourne: asset complet + extraction data

### 4B. Routes API

**Fichier** : `routes/api.php`

```php
// Assets CRUD
Route::get('/assets', [AssetController::class, 'index']);
Route::post('/assets', [AssetController::class, 'store'])->middleware('plan.limit:max_assets');
Route::post('/assets/ai-extract', [AssetController::class, 'aiExtract'])
    ->middleware(['throttle:ai-vision', 'plan.limit:max_ai_requests_daily']);
Route::post('/assets/ai-create', [AssetController::class, 'aiCreate'])
    ->middleware(['throttle:ai-vision', 'plan.limit:max_ai_requests_daily', 'plan.limit:max_assets']);
Route::get('/assets/{id}', [AssetController::class, 'show']);
Route::put('/assets/{id}', [AssetController::class, 'update']);
Route::delete('/assets/{id}', [AssetController::class, 'destroy']);
```

**Important** : Les routes `ai-extract` et `ai-create` AVANT `{id}` pour éviter la confusion avec un paramètre.

### 4C. Helpers de formatage

Méthodes privées `formatAsset()` et `formatAssetDetailed()` dans le contrôleur pour structurer les réponses JSON (suivre le pattern de `TaskController::download()`).

---

## Fichiers à créer/modifier

| Fichier | Action | Description |
|---------|--------|-------------|
| `app/DTOs/AiAssetExtractionResult.php` | **Créer** | DTO pour les résultats d'extraction AI |
| `app/Services/AiVisionService.php` | **Modifier** | +`extractAssetInfo(array $imagePaths)`, +prompts extraction (multi-image), +`resolveExtractionToIds()`, +`storeBase64Photo()` |
| `app/Http/Controllers/Api/AssetController.php` | **Créer** | CRUD complet + endpoints AI multi-image avec rétrocompat `photo`/`photos` |
| `routes/api.php` | **Modifier** | +7 routes assets |
| `config/gemini.php` | **Modifier** | Timeout augmenté à 120s (nécessaire pour multi-image) |
| ~~`resources/views/filament/forms/components/ai-photo-capture.blade.php`~~ | ~~Créer~~ | ~~Abandonné~~ — remplacé par le FileUpload natif de Filament |
| `app/Filament/App/Resources/AssetResource/Pages/ListAssets.php` | **Modifier** | +header action "Créer avec IA" multi-image (FileUpload natif) |
| `app/Filament/App/Resources/AssetResource/Pages/CreateAsset.php` | **Modifier** | +header action multi-image, +session hydration, +afterCreate boucle images |

## Patterns réutilisés

- **`AiIdentificationResult`** (`app/DTOs/`) → pattern pour le nouveau DTO
- **`AiVisionService::analyzePhoto()`** → pattern provider + fallback + logging
- **`AiVisionService::storeCapturedPhoto()`** → stockage photos AI existant
- **`AiVisionController`** → pattern quota check, validation, réponse JSON
- **`tag-scanner-modal.blade.php`** → pattern Alpine.js + `$wire` dans Filament
- **`Asset::generateAssetCode()`** → auto-génération code asset
- **`AssetCategory::getAllTagsForCategory()`** → récupération tags pour pré-remplir
- **`ChecksPlanLimits` trait** → vérification limites plan sur CreateAsset

## Vérification

1. **Filament Liste** → Cliquer "Créer avec IA" → uploader 1 à 5 photos → vérifier que l'AI extrait et combine les infos → vérifier la redirection vers CreateAsset avec le formulaire pré-rempli
2. **Filament Create** → Cliquer "Remplir avec l'IA" → uploader plusieurs photos → vérifier que les champs se remplissent
3. **Filament Create** → Vérifier que toutes les photos AI sont enregistrées comme AssetImage après la création (première = primary)
4. **API** → `POST /api/assets/ai-extract` avec `photos[]` (multipart) → vérifier la réponse JSON (extraction + resolved_ids + image_paths array)
5. **API** → `POST /api/assets/ai-create` avec `photos[]` + location_id → vérifier que l'asset est créé avec les bonnes données + images
6. **API rétrocompat** → `POST /api/assets/ai-extract` avec `photo` (singulier) → doit toujours fonctionner
7. **API** → `GET /api/assets` → vérifier la liste paginée avec filtres
8. **API** → `PUT /api/assets/{id}` → vérifier la mise à jour
9. **API** → `DELETE /api/assets/{id}` → vérifier le soft delete
10. **Quotas** → Vérifier que les limites plan sont respectées (daily, monthly, max_assets)
11. **Limite images** → Tenter 6 photos → vérifier erreur validation max:5
