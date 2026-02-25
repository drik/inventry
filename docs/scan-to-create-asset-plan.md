# Plan : Scan to Create Asset with AI

## Contexte

L'utilisateur veut permettre la création d'assets via l'IA, autant depuis le backoffice Filament que depuis l'API mobile. L'idée : prendre une photo d'un produit (boîte, étiquette...), l'IA analyse l'image et extrait les infos (nom, marque, modèle, numéro de série, SKU...) pour pré-remplir le formulaire de création d'asset.

**Placement du bouton "Créer avec IA"** : sur la page Liste des Assets ET la page Create Asset.
**Capture photo** : upload fichier ET caméra navigateur (MediaDevices API).
**API Mobile** : CRUD complet sur les assets + création via AI.
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

### 2A. Nouvelle méthode `extractAssetInfo()`

```php
public function extractAssetInfo(
    string $imagePath,
    Organization $organization,
    ?string $storagePath = null,
): array
```

Flux :
1. `prepareImage()` existant (resize 512px, JPEG 85%, base64)
2. Charger les catégories et fabricants de l'org comme contexte
3. Construire le prompt d'extraction (nouveau)
4. Sélectionner le provider via `getProvider()` existant
5. Appeler provider avec try/catch + fallback (même pattern que `analyzePhoto()`)
6. Parser la réponse en `AiAssetExtractionResult::fromAiResponse()`
7. Résoudre les noms suggérés en IDs via `resolveExtractionToIds()` (nouveau)
8. Logger via `logRecognition()` existant avec `use_case = 'asset_extraction'`, `task_id = null`
9. Retourner `['recognition_log_id', 'extraction' => DTO, 'resolved_ids' => [...], 'image_path']`

### 2B. Nouveau prompt d'extraction

Méthode `getAssetExtractionSystemPrompt()` :
```
Tu es un assistant spécialisé dans l'identification de produits et d'actifs physiques.
Tu analyses des photos d'objets pour en extraire les informations produit.
Réponds UNIQUEMENT en JSON valide.
Les scores de confiance vont de 0.0 à 1.0.
```

Méthode `buildExtractionPrompt($categories, $manufacturers)` :
```
Les catégories d'actifs sont : {liste}
Les fabricants connus sont : {liste}

Analyse la photo et retourne un JSON avec :
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

### 3A. Page ListAssets — Header Action

**Fichier** : `app/Filament/App/Resources/AssetResource/Pages/ListAssets.php`

Ajouter un header action "Créer avec IA" :
- Icône `heroicon-o-sparkles`, couleur `info`
- Visible si `config('ai-vision.enabled')` et quota non dépassé
- Ouvre un modal contenant le composant `ai-photo-capture`
- Au submit (via Livewire), appelle `analyzePhotoForAsset(base64)`
- Stocke le résultat en session, redirige vers CreateAsset

Méthode Livewire sur ListAssets :
```php
public function analyzePhotoForAsset(string $base64Image): void
{
    // Décoder, stocker, appeler AiVisionService::extractAssetInfo()
    // session()->put('ai_asset_extraction', $result)
    // redirect vers CreateAsset
}
```

### 3B. Page CreateAsset — Pré-remplissage + AI inline

**Fichier** : `app/Filament/App/Resources/AssetResource/Pages/CreateAsset.php`

Modifications :
1. **Header action** "Remplir avec l'IA" — même modal caméra/upload, mais au lieu de rediriger, remplit directement le form via `$this->form->fill()`
2. **Mount** — Vérifie si des données AI en session (`ai_asset_extraction`), si oui pré-remplit le formulaire
3. **`fillFormFromAiData()`** — Mappe les résultats AI vers les champs du formulaire (name, category_id, manufacturer_id, notes, tagValues)
4. **`afterCreate()`** — Si photo AI utilisée, crée un `AssetImage` primaire + met à jour l'`AiRecognitionLog` avec `selected_asset_id`

Propriétés ajoutées :
```php
public ?string $aiRecognitionLogId = null;
public ?string $aiImagePath = null;
```

### 3C. Composant Blade — ai-photo-capture

**Nouveau fichier** : `resources/views/filament/forms/components/ai-photo-capture.blade.php`

Composant Alpine.js avec :

1. **Sélecteur de mode** : onglets "Upload" / "Caméra"
2. **Mode Upload** : zone drag-and-drop + input file (accept="image/*")
3. **Mode Caméra** : `navigator.mediaDevices.getUserMedia()` avec vidéo live + bouton capturer
4. **Aperçu** : affiche l'image capturée/uploadée avec bouton "Reprendre"
5. **Bouton "Analyser avec l'IA"** : envoie le base64 via `$wire.call('analyzePhotoForAsset', base64)`
6. **État loading** : spinner pendant l'analyse
7. **Gestion d'erreurs** : messages pour caméra refusée, quota dépassé, etc.

Pattern Alpine.js similaire à `tag-scanner-modal.blade.php` (utilise `$wire` pour communiquer avec Livewire).

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

#### `POST /api/assets/ai-extract` — Extraction AI sans création
- Validation: photo (required, image, max 2048KB)
- Vérifie quotas AI (daily + monthly)
- Stocke la photo via `storeCapturedPhoto()`
- Appelle `AiVisionService::extractAssetInfo()`
- Retourne: `recognition_log_id`, `extraction` (DTO), `resolved_ids`, `image_path`, `usage`

#### `POST /api/assets/ai-create` — Extraction AI + Création
- Validation: photo (required) + location_id (required, AI ne peut pas le deviner) + overrides optionnels
- Appelle `extractAssetInfo()` puis crée l'asset
- Crée `AssetImage` primaire avec la photo capturée
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
| `app/Services/AiVisionService.php` | **Modifier** | +`extractAssetInfo()`, +prompts extraction, +`resolveExtractionToIds()`, +`storeBase64Photo()` |
| `app/Http/Controllers/Api/AssetController.php` | **Créer** | CRUD complet + endpoints AI |
| `routes/api.php` | **Modifier** | +7 routes assets |
| `resources/views/filament/forms/components/ai-photo-capture.blade.php` | **Créer** | Composant Alpine caméra + upload |
| `app/Filament/App/Resources/AssetResource/Pages/ListAssets.php` | **Modifier** | +header action "Créer avec IA" + méthode Livewire |
| `app/Filament/App/Resources/AssetResource/Pages/CreateAsset.php` | **Modifier** | +header action inline, +session hydration, +afterCreate hook |

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

1. **Filament Liste** → Cliquer "Créer avec IA" → uploader une photo → vérifier que l'AI extrait les infos → vérifier la redirection vers CreateAsset avec le formulaire pré-rempli
2. **Filament Create** → Cliquer "Remplir avec l'IA" → prendre une photo caméra → vérifier que les champs se remplissent
3. **Filament Create** → Vérifier que la photo AI est enregistrée comme image primaire après la création
4. **API** → `POST /api/assets/ai-extract` avec une photo → vérifier la réponse JSON (extraction + resolved_ids)
5. **API** → `POST /api/assets/ai-create` avec photo + location_id → vérifier que l'asset est créé avec les bonnes données
6. **API** → `GET /api/assets` → vérifier la liste paginée avec filtres
7. **API** → `PUT /api/assets/{id}` → vérifier la mise à jour
8. **API** → `DELETE /api/assets/{id}` → vérifier le soft delete
9. **Quotas** → Vérifier que les limites plan sont respectées (daily, monthly, max_assets)
10. **Caméra** → Tester sur mobile (facingMode: environment) et desktop (facingMode: user fallback)
