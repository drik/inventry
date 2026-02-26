# Plan : Auto-création d'entités suggérées par l'IA + Extraction enrichie

## Contexte

Actuellement, quand l'IA extrait les infos d'un asset depuis des photos, `resolveExtractionToIds()` fait un fuzzy match (exact puis LIKE) contre les entités existantes. Si aucun match n'est trouvé, le champ est retourné null avec la suggestion dans `unmatched_suggestions` — l'utilisateur doit créer manuellement l'entité manquante.

**Objectif** : auto-créer les entités manquantes avec un flag `suggested=true`, enrichir l'extraction avec les infos financières (factures), les emplacements et les fournisseurs. Appliquer la même logique au backoffice Filament et à l'API mobile.

---

## Phase 1 : Migration — colonne `suggested` sur 5 tables

**Nouveau fichier** : `database/migrations/2026_02_26_100000_add_suggested_to_entities.php`

Ajouter `suggested` (boolean, nullable, default null) sur :
- `asset_categories`
- `manufacturers`
- `asset_models`
- `locations`
- `suppliers`

```php
foreach (['asset_categories', 'manufacturers', 'asset_models', 'locations', 'suppliers'] as $table) {
    Schema::table($table, fn (Blueprint $t) => $t->boolean('suggested')->nullable()->default(null));
}
```

---

## Phase 2 : Modèles — ajouter `suggested` à fillable et casts

**5 fichiers à modifier** :

| Fichier | Ajout |
|---------|-------|
| `app/Models/AssetCategory.php` | `'suggested'` dans `$fillable` + cast `'suggested' => 'boolean'` |
| `app/Models/Manufacturer.php` | idem |
| `app/Models/AssetModel.php` | idem |
| `app/Models/Location.php` | idem |
| `app/Models/Supplier.php` | idem |

---

## Phase 3 : DTO — Enrichir `AiAssetExtractionResult`

**Fichier** : `app/DTOs/AiAssetExtractionResult.php`

Ajouter les champs :

```php
// Financier (depuis factures/reçus)
public ?float  $purchaseCost,
public ?string $purchaseDate,       // format YYYY-MM-DD
public ?string $warrantyExpiry,     // format YYYY-MM-DD (calculé si durée fournie)

// Emplacement
public ?string $suggestedLocation,  // nom d'emplacement/adresse détecté

// Fournisseur
public ?string $suggestedSupplier,  // nom du vendeur/fournisseur détecté
```

Mettre à jour `fromAiResponse()` et `toArray()` en conséquence.

---

## Phase 4 : Prompt — Extraction enrichie

**Fichier** : `app/Services/AiVisionService.php`

### 4A. `buildExtractionPrompt()`

Ajouter au prompt les champs attendus (en plus des existants) :

```
- "purchase_cost" : prix d'achat HT ou TTC si visible (nombre décimal, sans devise)
- "purchase_date" : date d'achat au format YYYY-MM-DD si visible
- "warranty_duration_months" : durée de garantie en mois si visible
- "warranty_expiry" : date de fin de garantie au format YYYY-MM-DD si visible
- "suggested_location" : emplacement/adresse/lieu mentionné dans le document
- "suggested_supplier" : nom du vendeur/fournisseur/magasin visible sur la facture
```

Ajouter au contexte du prompt la liste des **emplacements** et **fournisseurs** existants de l'org (comme on fait déjà pour catégories et fabricants).

### 4B. Calcul `warranty_expiry` dans `fromAiResponse()`

Si l'IA fournit `warranty_duration_months` et `purchase_date` mais pas `warranty_expiry` :
```php
$warrantyExpiry = $data['warranty_expiry'] ?? null;
if (! $warrantyExpiry && ($data['warranty_duration_months'] ?? null) && ($data['purchase_date'] ?? null)) {
    $warrantyExpiry = Carbon::parse($data['purchase_date'])
        ->addMonths((int) $data['warranty_duration_months'])
        ->toDateString();
}
```

---

## Phase 5 : `resolveExtractionToIds()` — Auto-création d'entités

**Fichier** : `app/Services/AiVisionService.php`

### Signature mise à jour

```php
protected function resolveExtractionToIds(
    AiAssetExtractionResult $extraction,
    Organization $org,
): array
```

### Retour enrichi

```php
return [
    'category_id' => ...,
    'manufacturer_id' => ...,
    'model_id' => ...,
    'location_id' => ...,     // NOUVEAU
    'supplier_id' => ...,     // NOUVEAU
    'unmatched_suggestions' => [...],
];
```

### Logique par entité

Pour **chaque entité** (catégorie, fabricant, modèle, emplacement, fournisseur) :

1. **Fuzzy match** : exact match sur `name`, puis `LIKE %name%` (existant pour cat/manuf/model)
2. **Si match trouvé** → retourner l'ID
3. **Si aucun match** → **créer l'entité** avec `suggested = true`, retourner le nouvel ID

#### Catégorie (existant + auto-create)
```php
$category = AssetCategory::withoutGlobalScopes()
    ->where('organization_id', $org->id)
    ->where('name', $extraction->suggestedCategory)->first()
    ?? AssetCategory::withoutGlobalScopes()
    ->where('organization_id', $org->id)
    ->where('name', 'LIKE', "%{$extraction->suggestedCategory}%")->first();

if (! $category && $extraction->suggestedCategory) {
    $category = AssetCategory::create([
        'organization_id' => $org->id,
        'name' => $extraction->suggestedCategory,
        'suggested' => true,
    ]);
}
```

#### Fabricant (existant + auto-create)
Même pattern. Scope : org + global (`whereNull('organization_id')`).
Si auto-create : `organization_id = $org->id`, `suggested = true`.

#### Modèle (existant + auto-create)
Scope par `manufacturer_id` trouvé. Si auto-create : avec `category_id` et `manufacturer_id` résolus.

#### Emplacement (NOUVEAU)
```php
if ($extraction->suggestedLocation) {
    $location = Location::withoutGlobalScopes()
        ->where('organization_id', $org->id)
        ->where('name', $extraction->suggestedLocation)->first()
        ?? Location::withoutGlobalScopes()
        ->where('organization_id', $org->id)
        ->where('name', 'LIKE', "%{$extraction->suggestedLocation}%")->first();

    if (! $location) {
        $location = Location::create([
            'organization_id' => $org->id,
            'name' => $extraction->suggestedLocation,
            'suggested' => true,
        ]);
    }
    $resolvedIds['location_id'] = $location->id;
}
```

#### Fournisseur (NOUVEAU)
Même pattern que l'emplacement.

---

## Phase 6 : Filament — `CreateAsset.php`

**Fichier** : `app/Filament/App/Resources/AssetResource/Pages/CreateAsset.php`

### 6A. `fillFormFromAiData()` — Pré-remplir les nouveaux champs

Ajouter dans le mapping :
```php
// Financier
if (! empty($extraction['purchase_cost'])) {
    $formData['purchase_cost'] = $extraction['purchase_cost'];
}
if (! empty($extraction['purchase_date'])) {
    $formData['purchase_date'] = $extraction['purchase_date'];
}
if (! empty($extraction['warranty_expiry'])) {
    $formData['warranty_expiry'] = $extraction['warranty_expiry'];
}

// Emplacement (résolu ou suggéré)
if (! empty($resolvedIds['location_id'])) {
    $formData['location_id'] = $resolvedIds['location_id'];
}

// Fournisseur (résolu ou suggéré)
if (! empty($resolvedIds['supplier_id'])) {
    $formData['supplier_id'] = $resolvedIds['supplier_id'];
}
```

### 6B. `afterCreate()` — Confirmer les entités suggérées

Après la création de l'asset, flip `suggested = false` sur toutes les entités référencées :

```php
protected function afterCreate(): void
{
    $record = $this->record;

    // Confirm suggested entities (implicit approval)
    $this->confirmSuggestedEntities($record);

    // ... existing image + log code ...
}

protected function confirmSuggestedEntities(Asset $asset): void
{
    foreach (['category', 'manufacturer', 'assetModel', 'location', 'supplier'] as $relation) {
        $entity = $asset->$relation;
        if ($entity && $entity->suggested === true) {
            $entity->update(['suggested' => false]);
        }
    }
}
```

### 6C. `EditAsset.php` — Même confirmation à la sauvegarde

**Fichier** : `app/Filament/App/Resources/AssetResource/Pages/EditAsset.php`

Ajouter `afterSave()` avec la même logique `confirmSuggestedEntities()`.

Pour éviter la duplication, extraire la méthode dans un **trait** :

**Nouveau fichier** : `app/Filament/App/Resources/AssetResource/Concerns/ConfirmsSuggestedEntities.php`

```php
trait ConfirmsSuggestedEntities
{
    protected function confirmSuggestedEntities(Asset $asset): void
    {
        foreach (['category', 'manufacturer', 'assetModel', 'location', 'supplier'] as $relation) {
            $entity = $asset->$relation;
            if ($entity && $entity->suggested === true) {
                $entity->update(['suggested' => false]);
            }
        }
    }
}
```

Utilisé par `CreateAsset` (dans `afterCreate`) et `EditAsset` (dans `afterSave`).

---

## Phase 7 : Filament — Indicateurs visuels dans les Select

**Fichier** : `app/Filament/App/Resources/AssetResource.php`

Pour chaque Select (category_id, manufacturer_id, model_id, location_id, supplier_id), ajouter un indicateur visuel sur les options suggérées :

```php
Forms\Components\Select::make('category_id')
    ->relationship('category', 'name')
    ->getOptionLabelFromRecordUsing(
        fn ($record) => $record->suggested
            ? "{$record->name} (Suggestion IA)"
            : $record->name
    )
    ->required()
    ->searchable()
    ->preload()
    ->live()
    // ... existing afterStateUpdated ...
```

Appliquer le même pattern aux 4 autres selects (manufacturer_id, model_id, location_id, supplier_id).

---

## Phase 8 : Filament — Colonnes et filtres sur les pages de liste

**10 fichiers à modifier** (App + Admin pour chaque entité) :

### App Panel — ajouter colonne + filtre :

| Fichier | Colonne | Filtre |
|---------|---------|--------|
| `app/Filament/App/Resources/AssetCategoryResource.php` | `IconColumn suggested` | `TernaryFilter suggested` |
| `app/Filament/App/Resources/ManufacturerResource.php` | idem | idem |
| `app/Filament/App/Resources/AssetModelResource.php` | idem | idem |
| `app/Filament/App/Resources/LocationResource.php` | idem | idem |
| `app/Filament/App/Resources/SupplierResource.php` | idem | idem |

Colonne pattern :
```php
Tables\Columns\IconColumn::make('suggested')
    ->label('IA')
    ->boolean()
    ->trueIcon('heroicon-o-sparkles')
    ->trueColor('warning')
    ->falseIcon('heroicon-o-check-circle')
    ->falseColor('success')
    ->default(false)
    ->toggleable()
    ->sortable(),
```

Filtre pattern :
```php
Tables\Filters\TernaryFilter::make('suggested')
    ->label('Suggestion IA')
    ->trueLabel('Non confirmées')
    ->falseLabel('Confirmées')
    ->placeholder('Toutes'),
```

### Admin Panel — ajouter la même colonne + filtre dans les 5 resources admin correspondantes :
- `app/Filament/Resources/AssetCategoryResource.php`
- `app/Filament/Resources/ManufacturerResource.php`
- `app/Filament/Resources/AssetModelResource.php`
- `app/Filament/Resources/LocationResource.php`
- `app/Filament/Resources/SupplierResource.php`

---

## Phase 9 : API — `AssetController`

**Fichier** : `app/Http/Controllers/Api/AssetController.php`

### 9A. `aiCreate()` — Utiliser les nouveaux champs résolus

```php
$asset = Asset::create([
    // ... existing ...
    'location_id' => $request->input('location_id') ?? $resolvedIds['location_id'],
    'supplier_id' => $resolvedIds['supplier_id'] ?? null,
    'purchase_cost' => $extraction->purchaseCost,
    'purchase_date' => $extraction->purchaseDate,
    'warranty_expiry' => $extraction->warrantyExpiry,
]);
```

**Note** : `location_id` reste required dans la validation. Si l'IA en suggère un, il sera dans `resolved_ids` mais le user peut toujours l'override.

Changement de validation pour `location_id` dans `aiCreate()` :
```php
// AVANT
'location_id' => 'required|string',
// APRÈS
'location_id' => 'nullable|string',
```
Si `location_id` n'est pas fourni par le user, on utilise celui de l'IA. Si l'IA n'en a pas trouvé non plus → erreur de validation custom.

### 9B. `aiCreate()` — Confirmer les entités suggérées

Après la création de l'asset :
```php
// Confirm suggested entities
foreach (['category', 'manufacturer', 'assetModel', 'location', 'supplier'] as $relation) {
    $entity = $asset->$relation;
    if ($entity && $entity->suggested === true) {
        $entity->update(['suggested' => false]);
    }
}
```

### 9C. `aiExtract()` — Retourner les nouveaux champs

La réponse inclut déjà `extraction` et `resolved_ids`. Les nouveaux champs (financiers, location_id, supplier_id) y seront automatiquement grâce aux modifications du DTO et de `resolveExtractionToIds()`.

### 9D. `store()` — Confirmer aussi à la création manuelle

Si l'user crée un asset manuellement via l'API avec une entité suggérée, la confirmer aussi :
```php
// After asset creation, confirm suggested entities
foreach (['category', 'manufacturer', 'assetModel', 'location', 'supplier'] as $relation) {
    $entity = $asset->$relation;
    if ($entity && $entity->suggested === true) {
        $entity->update(['suggested' => false]);
    }
}
```

---

## Phase 10 : Mobile App — Adaptations API

### Réponse `ai-extract` enrichie

La réponse JSON retourne désormais :
```json
{
  "extraction": {
    "suggested_name": "...",
    "suggested_category": "...",
    "suggested_brand": "...",
    "suggested_model": "...",
    "purchase_cost": 599.99,
    "purchase_date": "2026-01-15",
    "warranty_expiry": "2028-01-15",
    "suggested_location": "Bureau Paris 9e",
    "suggested_supplier": "LDLC",
    "...": "..."
  },
  "resolved_ids": {
    "category_id": "01HX...",
    "manufacturer_id": "01HX...",
    "model_id": "01HX...",
    "location_id": "01HX...",
    "supplier_id": "01HX..."
  }
}
```

### `ai-create` — `location_id` optionnel

- Si `location_id` fourni par le mobile → utilisé en priorité
- Sinon → utiliser `resolved_ids['location_id']` (peut être un emplacement suggéré par l'IA)
- Si ni l'un ni l'autre → erreur 422

### Indicateur `suggested` dans les réponses de liste

Pour l'app mobile, les endpoints de liste des entités (si existants) devraient inclure le flag `suggested` pour que le mobile puisse afficher un indicateur.

---

## Récapitulatif des fichiers

| Fichier | Action |
|---------|--------|
| `database/migrations/2026_02_26_100000_add_suggested_to_entities.php` | **Créer** |
| `app/Models/AssetCategory.php` | **Modifier** — +suggested fillable/cast |
| `app/Models/Manufacturer.php` | **Modifier** — idem |
| `app/Models/AssetModel.php` | **Modifier** — idem |
| `app/Models/Location.php` | **Modifier** — idem |
| `app/Models/Supplier.php` | **Modifier** — idem |
| `app/DTOs/AiAssetExtractionResult.php` | **Modifier** — +5 champs (financier, location, supplier) |
| `app/Services/AiVisionService.php` | **Modifier** — prompt enrichi, resolveExtractionToIds auto-create |
| `app/Filament/App/Resources/AssetResource/Concerns/ConfirmsSuggestedEntities.php` | **Créer** — trait partagé |
| `app/Filament/App/Resources/AssetResource/Pages/CreateAsset.php` | **Modifier** — fillFormFromAiData enrichi, afterCreate confirm |
| `app/Filament/App/Resources/AssetResource/Pages/EditAsset.php` | **Modifier** — afterSave confirm |
| `app/Filament/App/Resources/AssetResource.php` | **Modifier** — indicateurs visuels Select |
| `app/Filament/App/Resources/AssetCategoryResource.php` | **Modifier** — colonne+filtre suggested |
| `app/Filament/App/Resources/ManufacturerResource.php` | **Modifier** — idem |
| `app/Filament/App/Resources/AssetModelResource.php` | **Modifier** — idem |
| `app/Filament/App/Resources/LocationResource.php` | **Modifier** — idem |
| `app/Filament/App/Resources/SupplierResource.php` | **Modifier** — idem |
| `app/Filament/Resources/AssetCategoryResource.php` | **Modifier** — colonne+filtre (admin) |
| `app/Filament/Resources/ManufacturerResource.php` | **Modifier** — idem |
| `app/Filament/Resources/AssetModelResource.php` | **Modifier** — idem |
| `app/Filament/Resources/LocationResource.php` | **Modifier** — idem |
| `app/Filament/Resources/SupplierResource.php` | **Modifier** — idem |
| `app/Http/Controllers/Api/AssetController.php` | **Modifier** — aiCreate enrichi, confirm entities |

---

## Vérification

1. **Filament Create** → uploader photo d'un produit inconnu → vérifier que catégorie/fabricant/modèle sont auto-créés avec `suggested=true` → formulaire pré-rempli → enregistrer → vérifier que `suggested` passe à `false`
2. **Filament Create** → uploader une facture → vérifier que prix, date d'achat, garantie, fournisseur sont extraits
3. **Filament Edit** → modifier un asset avec entité suggérée → sauvegarder → vérifier confirmation
4. **Filament Select** → vérifier que les options suggérées affichent "(Suggestion IA)"
5. **Filament List** → sur chaque page de liste (catégories, fabricants, modèles, emplacements, fournisseurs) → vérifier la colonne "IA" et le filtre
6. **API** → `POST /api/assets/ai-extract` avec facture → vérifier les champs financiers + supplier + location dans la réponse
7. **API** → `POST /api/assets/ai-create` sans `location_id` mais avec photo ayant un lieu → vérifier que location est auto-créée
8. **API** → `POST /api/assets/ai-create` avec entités nouvelles → vérifier qu'elles sont créées `suggested=true` puis confirmées à `false`
9. **Doublons** → Scanner le même produit 2 fois → vérifier que l'entité suggérée existante est réutilisée (pas de doublon)
10. **Suppression** → Filtrer les entités suggérées non confirmées → pouvoir les supprimer
