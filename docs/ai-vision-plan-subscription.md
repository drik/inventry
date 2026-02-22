# Reconnaissance d'Assets par IA (Vision AI) — Intégration Souscription

## Contexte et Problématique

Lors de l'inventaire physique, les agents de terrain rencontrent régulièrement des situations où le scan barcode/NFC échoue :
- **Étiquette endommagée ou manquante** : le barcode est illisible, arraché ou effacé
- **Asset inconnu** : l'agent ne sait pas identifier l'objet devant lui
- **Doute sur l'identité** : l'agent n'est pas sûr que l'asset scanné correspond physiquement à ce qu'indique la base

**Objectif** : Intégrer une fonctionnalité de reconnaissance par IA permettant à l'agent de **prendre une photo d'un asset** et d'obtenir :
1. Une **identification** de l'objet (catégorie, marque, modèle, textes détectés)
2. Une **correspondance** avec les assets connus de l'organisation (via comparaison d'images)
3. Une **vérification** optionnelle (la photo correspond-elle à l'asset enregistré ?)

---

## Limites IA par plan de souscription

Les quotas IA sont gérés dynamiquement via le système de souscription existant (`PlanLimitService`). Chaque plan définit ses limites dans le champ JSON `limits` de la table `plans`.

| Plan | Requêtes IA/jour | Requêtes IA/mois | Accès API mobile |
|------|-----------------|-----------------|-----------------|
| **Freemium** (0€) | 2 | 10 | Oui |
| **Basic** (5€/mois) | 5 | 50 | Oui |
| **Pro** (35€/mois) | 50 | 500 | Oui |
| **Premium** (250€/mois) | Illimité (-1) | Illimité (-1) | Oui |

**Stratégie Freemium** : Le plan gratuit donne un accès minimal à l'IA (2 req/jour, 10/mois) pour permettre aux utilisateurs de tester la fonctionnalité et les inciter à passer à un plan supérieur.

**Infrastructure existante utilisée** :
- `app/Enums/PlanFeature.php` : `MaxAiRequestsDaily`, `MaxAiRequestsMonthly`
- `app/Services/PlanLimitService.php` : `canCreate()`, `getCurrentUsage()`, `getUsageStats()`
- `app/Models/AiUsageLog.php` : table `ai_usage_logs` pour le comptage des quotas
- `app/Http/Middleware/CheckPlanLimit.php` : middleware `plan.limit` pour les routes API

---

## Choix technologique : Architecture Multi-Provider (Gemini Flash + OpenAI GPT-4o) ✓

### Comparaison des fournisseurs évalués

| Critère | Google Gemini 2.5 Flash | OpenAI GPT-4o | Google Cloud Vision | AWS Rekognition | Azure Computer Vision | Claude Vision |
|---------|------------------------|---------------|--------------------|-----------------|-----------------------|---------------|
| **Compréhension contextuelle** | Excellente (prompt custom) | Excellente (prompt custom) | Labels génériques | Labels génériques | Labels génériques | Excellente (prompt custom) |
| **Comparaison d'images** | Multi-images en 1 appel | Multi-images en 1 appel | Non natif (Vertex AI) | Custom Labels uniquement | Non natif | Multi-images en 1 appel |
| **OCR + identification combinés** | Un seul appel | Un seul appel | Appels séparés | Besoin Textract | Appels séparés | Un seul appel |
| **Sortie JSON structurée** | JSON mode | `response_format: json_schema` | Format fixe | Format fixe | Format fixe | Tool use |
| **Coût par requête** (1 photo + 5 refs) | **~$0.002** | ~$0.015 | ~$0.009 + Vertex $50-100/mois | ~$0.004 + Custom Labels $2880/mois | ~$0.006 | ~$0.005 (Haiku) |
| **Coût 100 req/jour/mois** | **~$6** | ~$45 | ~$27 + infra | ~$12 + infra | ~$18 | ~$15 (Haiku) |
| **Latence** | 1-3 secondes | 2-4 secondes | 0.5-1s (pas de matching) | 1-2s (pas de matching) | 1-2s (pas de matching) | 2-4 secondes |
| **Intégration Laravel** | `google-gemini-php/laravel` | `openai-php/laravel` | `google/cloud-vision` | `aws/aws-sdk-php` | SDK REST | SDK PHP |

### Pourquoi les services spécialisés (Cloud Vision, AWS, Azure) sont insuffisants seuls

- **Pas de comparaison d'images native** : nécessitent des infras lourdes (Vertex AI Matching Engine, Custom Labels) → coûts cachés x10
- **Labels génériques** : retournent "laptop" au lieu de "Dell Latitude 5540 - Ordinateur portable" → pas de contexte métier
- **Appels multiples** : nécessitent 3-4 appels séparés (labels + OCR + logos + matching) vs 1 seul appel LLM
- **Pas de sortie personnalisable** : format de réponse fixe, impossible d'adapter au domaine

### Architecture retenue : Provider différencié par plan

Le choix du provider IA dépend du plan de souscription de l'organisation :

| Plan | Provider | Fallback | Qualité | Coût/requête |
|------|----------|----------|---------|-------------|
| **Freemium** | Gemini Flash uniquement | Aucun | Bonne | ~$0.002 |
| **Basic** | Gemini Flash | GPT-4o si confiance < 0.5 | Bonne → Excellente | ~$0.002 à ~$0.015 |
| **Pro** | Gemini Flash | GPT-4o si confiance < 0.5 | Bonne → Excellente | ~$0.002 à ~$0.015 |
| **Premium** | GPT-4o directement | Gemini Flash si GPT-4o échoue | Excellente | ~$0.015 |

```
Requête IA
    │
    ├── Plan Freemium
    │   └── Gemini Flash uniquement
    │       → ~$0.002/requête, rapide (1-3s)
    │       → Pas de fallback (coût minimal, 2 req/jour max)
    │
    ├── Plan Basic / Pro
    │   ├── Gemini Flash (primaire)
    │   │   → ~$0.002/requête, bon rapport qualité/prix
    │   └── Fallback GPT-4o si confiance < 0.5
    │       → ~$0.015/requête, meilleure qualité
    │       → Déclenché aussi si Gemini échoue (erreur API, timeout)
    │
    └── Plan Premium
        ├── GPT-4o directement (primaire)
        │   → ~$0.015/requête, qualité maximale garantie
        └── Fallback Gemini Flash si GPT-4o échoue
            → Assure la disponibilité du service
```

**Justification** : Cette approche différencie la qualité de service par plan. Les utilisateurs Premium paient pour la meilleure expérience et reçoivent systématiquement les analyses GPT-4o (plus précises), avec fallback sur Gemini Flash en cas d'indisponibilité. Les plans Basic/Pro utilisent Gemini Flash (excellent rapport qualité/prix) avec un filet de sécurité GPT-4o pour les cas difficiles. Le plan Freemium reste sur Gemini seul pour maîtriser les coûts.

---

## Cas d'usage détaillés

### Cas 1 : Identifier un asset inconnu (`identify`)

**Déclencheur** : Le scan barcode retourne "Asset inconnu" ou l'agent ne trouve pas de code lisible.

**Flux** :
1. L'agent appuie sur "Identifier par photo"
2. L'écran de capture s'ouvre (vue caméra plein écran)
3. L'agent prend une photo de l'objet
4. La photo est envoyée au serveur → le provider est sélectionné selon le plan (Gemini Flash ou GPT-4o)
5. Résultat affiché : catégorie suggérée, marque/modèle détectés, textes lus (numéros de série)

### Cas 2 : Correspondance avec les assets connus (`match`)

**Combiné avec le Cas 1** dans un seul appel API.

**Flux** :
1. Le serveur sélectionne les assets candidats (même emplacement, avec image)
2. Envoie la photo capturée + jusqu'à 8 images de référence au LLM (Gemini Flash / GPT-4o)
3. Le LLM retourne les correspondances avec scores de confiance
4. L'agent voit la liste des correspondances et peut sélectionner le bon asset

### Cas 3 : Vérifier l'identité d'un asset (`verify`)

**Déclencheur** : L'asset a été trouvé par barcode, mais l'agent doute de la correspondance physique.

**Flux** :
1. Après un scan barcode réussi, l'agent appuie sur "Vérifier par photo"
2. Prend une photo de l'objet physique
3. Le serveur compare avec l'image principale de l'asset enregistré
4. Résultat : "Correspond" / "Ne correspond pas" avec explication

---

## API REST (Backend Laravel)

### Installation

```bash
composer require google-gemini-php/laravel    # Provider primaire (Gemini Flash)
composer require openai-php/laravel           # Provider fallback (GPT-4o)
php artisan vendor:publish --provider="GeminiAPI\Laravel\ServiceProvider"
php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"
```

### Configuration

**`.env`** :
```
# Provider primaire : Google Gemini
GEMINI_API_KEY=AIza...
GEMINI_VISION_MODEL=gemini-2.5-flash

# Provider fallback : OpenAI
OPENAI_API_KEY=sk-...
OPENAI_VISION_MODEL=gpt-4o

# Configuration IA Vision
AI_VISION_ENABLED=true
AI_VISION_PRIMARY_PROVIDER=gemini
AI_VISION_FALLBACK_PROVIDER=openai
AI_VISION_FALLBACK_CONFIDENCE_THRESHOLD=0.5
AI_VISION_MAX_IMAGE_SIZE_KB=2048
```

> **Note** : La variable `AI_VISION_DAILY_LIMIT_PER_ORG` n'existe plus. Les quotas sont gérés par le plan de souscription de l'organisation via `PlanLimitService`.

**Nouveau fichier `config/ai-vision.php`** :
```php
return [
    'enabled' => env('AI_VISION_ENABLED', false),

    // Provider primaire et fallback
    'primary_provider' => env('AI_VISION_PRIMARY_PROVIDER', 'gemini'),
    'fallback_provider' => env('AI_VISION_FALLBACK_PROVIDER', 'openai'),
    'fallback_confidence_threshold' => (float) env('AI_VISION_FALLBACK_CONFIDENCE_THRESHOLD', 0.5),

    // Configuration Gemini (provider primaire)
    'gemini' => [
        'model' => env('GEMINI_VISION_MODEL', 'gemini-2.5-flash'),
        'max_tokens' => 1000,
    ],

    // Configuration OpenAI (provider fallback)
    'openai' => [
        'model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
        'max_tokens' => 1000,
    ],

    'limits' => [
        // Les quotas quotidiens/mensuels sont gérés par le plan de souscription
        // via PlanLimitService (PlanFeature::MaxAiRequestsDaily / MaxAiRequestsMonthly)
        'max_image_size_kb' => (int) env('AI_VISION_MAX_IMAGE_SIZE_KB', 2048),
        'max_reference_images' => 8,
    ],
];
```

### Nouvelles routes API

```php
// routes/api.php — dans le groupe auth:sanctum
Route::post('/tasks/{taskId}/ai-identify', [AiVisionController::class, 'identify'])
    ->middleware('plan.limit:max_ai_requests_daily');
Route::post('/tasks/{taskId}/ai-verify', [AiVisionController::class, 'verify'])
    ->middleware('plan.limit:max_ai_requests_daily');
Route::post('/tasks/{taskId}/ai-confirm', [AiVisionController::class, 'confirm']);
// ai-confirm ne consomme pas de quota IA (confirmation utilisateur uniquement)
```

Le middleware `plan.limit:max_ai_requests_daily` (déjà enregistré dans `bootstrap/app.php`) vérifie automatiquement le quota quotidien du plan de l'organisation via `CheckPlanLimit` → `PlanLimitService::canCreate()`. Il retourne un 403 avec un message dynamique si la limite est atteinte.

### Endpoint : `POST /api/tasks/{taskId}/ai-identify`

Identification + correspondance combinées dans un seul appel.

**Request** : `multipart/form-data`
- `photo` : fichier image (JPEG/PNG, max 2 Mo)

**Response 200** :
```json
{
  "recognition_log_id": "01JN...",
  "identification": {
    "suggested_category": "Ordinateurs portables",
    "suggested_brand": "Dell",
    "suggested_model": "Latitude 5540",
    "detected_text": ["SN: ABCD-1234-EFGH", "Service Tag: 7X8Y9Z"],
    "confidence": 0.92,
    "description": "Ordinateur portable Dell Latitude, couleur gris, écran 15 pouces"
  },
  "matches": [
    {
      "asset_id": "01JN...",
      "asset_name": "Dell Latitude 5540",
      "asset_code": "AST-00003",
      "category_name": "Ordinateurs portables",
      "location_name": "Siège - Lomé",
      "primary_image_url": "https://...",
      "confidence": 0.89,
      "reasoning": "Le modèle et la couleur correspondent. Le numéro de série détecté correspond partiellement."
    }
  ],
  "has_strong_match": true,
  "inventory_status": {
    "AST-00003": "expected",
    "AST-00004": "found"
  },
  "usage": {
    "daily": { "current": 3, "limit": 5, "remaining": 2 },
    "monthly": { "current": 28, "limit": 50, "remaining": 22 }
  }
}
```

**Response 403** (quota du plan dépassé — via middleware `plan.limit`) :
```json
{
  "message": "Limite atteinte : votre plan Basic autorise 5 Requêtes IA / jour. Passez à un plan supérieur pour en ajouter davantage.",
  "error": "plan_limit_reached",
  "feature": "max_ai_requests_daily"
}
```

### Endpoint : `POST /api/tasks/{taskId}/ai-verify`

Vérifie qu'une photo correspond à un asset spécifique.

**Request** : `multipart/form-data`
- `photo` : fichier image
- `asset_id` : ULID de l'asset à vérifier

**Response 200** :
```json
{
  "recognition_log_id": "01JN...",
  "is_match": true,
  "confidence": 0.94,
  "reasoning": "L'appareil photographié correspond à l'image de référence : même modèle Dell Latitude, même couleur, même autocollant d'inventaire visible.",
  "discrepancies": [],
  "usage": {
    "daily": { "current": 4, "limit": 5, "remaining": 1 },
    "monthly": { "current": 29, "limit": 50, "remaining": 21 }
  }
}
```

### Endpoint : `POST /api/tasks/{taskId}/ai-confirm`

L'utilisateur confirme ou rejette la suggestion IA. **Ne consomme pas de quota IA.**

**Request** :
```json
{
  "recognition_log_id": "01JN...",
  "asset_id": "01JN...",
  "action": "matched"
}
```

- `action` : `matched` (confirme la correspondance → marque l'item comme Found), `unexpected` (ajoute comme inattendu), `dismissed` (annule)

**Response 200** : Même format que `POST /api/tasks/{taskId}/scan` (retourne l'item créé/mis à jour)

**Logique backend** :
- Si `action = matched` : appeler `InventoryScanService::markAsFound()` avec `identification_method = ai_vision`
- Si `action = unexpected` : appeler `InventoryScanService::addUnexpected()` avec `identification_method = ai_vision`
- Si `action = dismissed` : juste logger, ne rien modifier
- Dans tous les cas : mettre à jour `AiRecognitionLog` avec `selected_asset_id` et `selected_action`

---

## Migrations

### Migration 1 : Table `ai_recognition_logs`

```php
// database/migrations/xxxx_create_ai_recognition_logs_table.php

Schema::create('ai_recognition_logs', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignUlid('task_id')->nullable()->constrained('inventory_tasks')->nullOnDelete();
    $table->foreignUlid('user_id')->constrained('users');
    $table->string('captured_image_path');           // chemin de la photo capturée
    $table->string('use_case');                       // 'identify', 'match', 'verify'
    $table->string('provider');                        // 'gemini' ou 'openai'
    $table->string('model');                           // 'gemini-2.5-flash', 'gpt-4o', etc.
    $table->boolean('used_fallback')->default(false);  // true si fallback déclenché
    $table->json('ai_response');                      // réponse complète du LLM
    $table->json('matched_asset_ids')->nullable();    // IDs des assets proposés
    $table->foreignUlid('selected_asset_id')->nullable()->constrained('assets')->nullOnDelete();
    $table->string('selected_action')->nullable();    // 'matched', 'unexpected', 'dismissed'
    $table->integer('prompt_tokens')->nullable();
    $table->integer('completion_tokens')->nullable();
    $table->decimal('estimated_cost_usd', 8, 6)->nullable();
    $table->integer('latency_ms')->nullable();
    $table->timestamps();
});
```

> **Note** : La table `ai_usage_logs` existe déjà (créée par le système de souscription). Elle est utilisée par `PlanLimitService` pour compter les quotas IA quotidiens/mensuels. Chaque requête AI Vision crée une entrée dans les DEUX tables : `ai_usage_logs` (comptage quota) + `ai_recognition_logs` (détail reconnaissance).

### Migration 2 : Colonnes supplémentaires sur `inventory_items`

```php
// database/migrations/xxxx_add_ai_fields_to_inventory_items_table.php

Schema::table('inventory_items', function (Blueprint $table) {
    $table->string('identification_method')->default('barcode')->after('condition_notes');
    // Valeurs possibles : 'barcode', 'nfc', 'ai_vision', 'manual'
    $table->foreignUlid('ai_recognition_log_id')->nullable()->after('identification_method')
        ->constrained('ai_recognition_logs')->nullOnDelete();
    $table->decimal('ai_confidence', 5, 4)->nullable()->after('ai_recognition_log_id');
});
```

---

## Fichiers backend à créer

| Fichier | Description |
|---------|------------|
| `config/ai-vision.php` | Configuration multi-provider (Gemini + OpenAI, taille images, fallback) |
| `app/Services/AiVision/VisionProviderInterface.php` | Interface commune pour les providers |
| `app/Services/AiVision/GeminiVisionProvider.php` | Provider Gemini Flash (primaire) |
| `app/Services/AiVision/OpenAiVisionProvider.php` | Provider OpenAI GPT-4o (fallback) |
| `app/Services/AiVisionService.php` | Orchestrateur : sélection provider, fallback, candidats, prompt, logging |
| `app/Http/Controllers/Api/AiVisionController.php` | Contrôleur API (identify, verify, confirm) |
| `app/Models/AiRecognitionLog.php` | Modèle Eloquent pour le log détaillé des requêtes IA |
| `app/DTOs/AiIdentificationResult.php` | DTO : résultat d'identification |
| `app/DTOs/AiMatchResult.php` | DTO : résultat de correspondance |
| `app/DTOs/AiVerificationResult.php` | DTO : résultat de vérification |
| `database/migrations/xxxx_create_ai_recognition_logs_table.php` | Migration table de logs détaillés |
| `database/migrations/xxxx_add_ai_fields_to_inventory_items_table.php` | Migration colonnes IA |

## Fichiers backend à modifier

| Fichier | Modification |
|---------|-------------|
| `routes/api.php` | Ajouter les 3 routes `/ai-identify`, `/ai-verify`, `/ai-confirm` avec middleware `plan.limit:max_ai_requests_daily` |
| `app/Services/InventoryScanService.php` | Ajouter paramètre `identification_method` aux méthodes `markAsFound()` et `addUnexpected()` |
| `app/Http/Controllers/Api/SyncController.php` | Accepter `identification_method`, `ai_recognition_log_id`, `ai_confidence` dans le payload sync |
| `app/Providers/AppServiceProvider.php` | Ajouter rate limiter `ai-vision` (10 req/min par org) |

---

## Service `AiVisionService` — Détail technique

### Méthodes publiques

```php
class AiVisionService
{
    public function __construct(
        protected GeminiVisionProvider $geminiProvider,
        protected OpenAiVisionProvider $openAiProvider,
        protected PlanLimitService $planLimitService,
    ) {}

    // Cas 1+2 combinés : identifier + chercher des correspondances
    public function analyzePhoto(
        string $imagePath,
        Organization $organization,
        ?string $locationId = null,
        ?string $taskId = null,
        bool $forceHighQuality = false   // force le fallback GPT-4o
    ): AiAnalysisResult;

    // Cas 3 : vérifier qu'une photo correspond à un asset précis
    public function verifyAssetIdentity(
        string $capturedImagePath,
        Asset $asset
    ): AiVerificationResult;

    // Vérification du quota via PlanLimitService
    public function canMakeRequest(Organization $org): bool;

    // Sélection du provider selon le plan
    protected function getProvider(Organization $org): VisionProviderInterface;

    // Vérifie si un fallback est nécessaire après analyse
    protected function shouldFallback(Organization $org, array $result): bool;
}
```

### Vérification du quota — Intégration `PlanLimitService`

```php
/**
 * Vérifie les quotas quotidien ET mensuel du plan de l'organisation.
 * Utilise PlanLimitService qui compte via la table ai_usage_logs.
 */
public function canMakeRequest(Organization $org): bool
{
    return $this->planLimitService->canCreate($org, PlanFeature::MaxAiRequestsDaily)
        && $this->planLimitService->canCreate($org, PlanFeature::MaxAiRequestsMonthly);
}
```

**Comptage existant** (`PlanLimitService::getCurrentUsage()`, lignes 48-54) :
```php
PlanFeature::MaxAiRequestsDaily => AiUsageLog::where('organization_id', $org->id)
    ->whereDate('created_at', today())
    ->count(),
PlanFeature::MaxAiRequestsMonthly => AiUsageLog::where('organization_id', $org->id)
    ->whereMonth('created_at', now()->month)
    ->whereYear('created_at', now()->year)
    ->count(),
```

### Double logging

Après chaque appel IA réussi, le service crée **2 entrées** :

```php
// 1. Comptage quota du plan (table existante ai_usage_logs)
AiUsageLog::create([
    'organization_id' => $org->id,
    'user_id' => auth()->id(),
    'feature' => 'ai_vision',
    'tokens_used' => $promptTokens + $completionTokens,
]);

// 2. Détail de la reconnaissance (nouvelle table ai_recognition_logs)
$log = AiRecognitionLog::create([
    'organization_id' => $org->id,
    'task_id' => $taskId,
    'user_id' => auth()->id(),
    'captured_image_path' => $imagePath,
    'use_case' => $useCase,
    'provider' => $provider->getProviderName(),
    'model' => $provider->getModelName(),
    'used_fallback' => $usedFallback,
    'ai_response' => $response,
    'matched_asset_ids' => $matchedIds,
    'prompt_tokens' => $promptTokens,
    'completion_tokens' => $completionTokens,
    'estimated_cost_usd' => $estimatedCost,
    'latency_ms' => $latencyMs,
]);
```

### Interface `VisionProviderInterface`

```php
interface VisionProviderInterface
{
    public function analyze(array $images, string $prompt, array $options = []): array;
    public function getProviderName(): string;   // 'gemini' ou 'openai'
    public function getModelName(): string;       // 'gemini-2.5-flash', 'gpt-4o'
}
```

**Fichiers à créer** :
- `app/Services/AiVision/VisionProviderInterface.php`
- `app/Services/AiVision/GeminiVisionProvider.php` — appels via `google-gemini-php/laravel`
- `app/Services/AiVision/OpenAiVisionProvider.php` — appels via `openai-php/laravel`
- `app/Services/AiVisionService.php` — orchestrateur avec logique de fallback

### Sélection du provider selon le plan

```php
protected function getProvider(Organization $org): VisionProviderInterface
{
    $plan = $this->planLimitService->getEffectivePlan($org);

    // Premium → GPT-4o directement (qualité maximale)
    if ($plan->slug === 'premium') {
        return $this->openAiProvider;
    }

    // Freemium, Basic, Pro → Gemini Flash (primaire)
    return $this->geminiProvider;
}

protected function shouldFallback(Organization $org, array $result): bool
{
    $plan = $this->planLimitService->getEffectivePlan($org);

    // Freemium → jamais de fallback (coût minimal)
    if ($plan->slug === 'freemium') {
        return false;
    }

    // Premium → fallback sur Gemini uniquement si GPT-4o échoue (géré par try/catch)
    // Basic / Pro → fallback GPT-4o si confiance faible
    return $result['identification']['confidence'] < config('ai-vision.fallback_confidence_threshold');
}

protected function getFallbackProvider(Organization $org): VisionProviderInterface
{
    $plan = $this->planLimitService->getEffectivePlan($org);

    // Premium : fallback = Gemini (le primaire des autres plans)
    if ($plan->slug === 'premium') {
        return $this->geminiProvider;
    }

    // Basic / Pro : fallback = GPT-4o
    return $this->openAiProvider;
}
```

### Logique de fallback dans `analyzePhoto()`

```php
// Dans AiVisionService::analyzePhoto()
$provider = $this->getProvider($organization);

try {
    $result = $provider->analyze($images, $prompt);
} catch (\Exception $e) {
    // Si le provider primaire échoue → fallback (sauf Freemium)
    $plan = $this->planLimitService->getEffectivePlan($organization);
    if ($plan->slug === 'freemium') {
        throw $e;
    }
    $provider = $this->getFallbackProvider($organization);
    $result = $provider->analyze($images, $prompt);
    $usedFallback = true;
}

// Si confiance faible → tenter le fallback (Basic/Pro uniquement)
if (!$usedFallback && $this->shouldFallback($organization, $result)) {
    $fallbackProvider = $this->getFallbackProvider($organization);
    $result = $fallbackProvider->analyze($images, $prompt);
    $usedFallback = true;
}
```

### Stratégie de sélection des candidats (pour la comparaison d'images)

Avant d'envoyer au LLM, le service doit sélectionner intelligemment les images de référence :

1. **Priorité 1** : Assets `Expected` dans la session courante, au même emplacement (`location_id`), ayant une `primaryImage` → les plus probables
2. **Priorité 2** : Autres assets au même emplacement avec image
3. **Priorité 3** : Assets de toute l'organisation avec image (si < 8 candidats trouvés)
4. **Maximum** : 8 images de référence (configurable via `config('ai-vision.limits.max_reference_images')`)

### Prétraitement des images

Avant envoi à l'API :
- Redimensionner à max **1024px** sur le côté le plus long (via `Intervention\Image`)
- Compresser en JPEG qualité 85%
- Encoder en base64 pour l'appel API
- Cela réduit le coût en tokens de ~75% par rapport à l'envoi d'une photo brute

### Prompt LLM (commun Gemini / OpenAI)

```
Système :
Tu es un assistant spécialisé dans l'identification d'actifs physiques pour un
inventaire d'entreprise. Tu analyses des photos prises par des employés pendant
un inventaire physique. Tu dois identifier l'objet et le comparer aux images de
référence fournies.

Les catégories d'actifs de cette organisation sont :
{liste dynamique depuis AssetCategory::pluck('name')}

Pour chaque photo analysée, retourne un JSON avec :
1. "identification" : catégorie suggérée, marque, modèle, textes détectés
   (numéros de série, références), confiance (0-1), description courte
2. "matches" : pour chaque image de référence (ref_1, ref_2...),
   indique si elle correspond avec un score de confiance et une explication

Réponds UNIQUEMENT en JSON valide.

Utilisateur :
Photo capturée : [image base64]
Images de référence :
- ref_1 (AST-00003, "Dell Latitude 5540", Ordinateurs portables) : [image]
- ref_2 (AST-00015, "HP EliteBook 840 G10", Ordinateurs portables) : [image]
...

Identifie l'objet dans la photo capturée et compare-le aux références.
```

### Schéma JSON de sortie forcé

```json
{
  "identification": {
    "suggested_category": "string",
    "suggested_brand": "string|null",
    "suggested_model": "string|null",
    "detected_text": ["string"],
    "confidence": 0.0,
    "description": "string"
  },
  "matches": [
    {
      "reference_key": "ref_1",
      "confidence": 0.0,
      "reasoning": "string"
    }
  ]
}
```

---

## Contrôle des coûts et limites

### 3 niveaux de protection

1. **Middleware `plan.limit`** (par requête) — vérifie le quota quotidien du plan :
```php
// Routes API avec middleware existant (app/Http/Middleware/CheckPlanLimit.php)
Route::post('/tasks/{taskId}/ai-identify', ...)->middleware('plan.limit:max_ai_requests_daily');
```
Retourne automatiquement un 403 avec message dynamique :
```json
{
    "message": "Limite atteinte : votre plan Basic autorise 5 Requêtes IA / jour. Passez à un plan supérieur pour en ajouter davantage.",
    "error": "plan_limit_reached",
    "feature": "max_ai_requests_daily"
}
```

2. **Vérification mensuelle** (dans le contrôleur) :
```php
// AiVisionController — vérification complémentaire du quota mensuel
if (! $aiVisionService->canMakeRequest($org)) {
    return response()->json([
        'message' => $planLimitService->getLimitReachedMessage(PlanFeature::MaxAiRequestsMonthly, $org),
        'error' => 'plan_limit_reached',
        'feature' => 'max_ai_requests_monthly',
    ], 403);
}
```

3. **Rate limiter Laravel** (anti-abus, par minute) :
```php
// AppServiceProvider::boot()
RateLimiter::for('ai-vision', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()->organization_id);
});
```

4. **Validation de taille** : rejet des images > `max_image_size_kb` (2 Mo par défaut)

### Estimation des coûts par plan (provider différencié)

| Plan | Volume max/jour | Provider primaire | Fallback (~10% des req) | **Coût mensuel max** |
|------|----------------|-------------------|------------------------|---------------------|
| Freemium (2/jour) | 2 req | Gemini Flash : $0.12 | Aucun | **~$0.12** |
| Basic (5/jour) | 5 req | Gemini Flash : $0.27 | GPT-4o : $0.23 | **~$0.50** |
| Pro (50/jour) | 50 req | Gemini Flash : $2.70 | GPT-4o : $2.25 | **~$5** |
| Premium (illimité, ~200/jour) | ~200 req | GPT-4o : $90 | Gemini Flash (erreur) : négligeable | **~$90** |

- Freemium : Gemini Flash uniquement → coût le plus bas possible
- Basic / Pro : Gemini Flash + ~10% fallback GPT-4o → bon rapport qualité/prix
- Premium : GPT-4o 100% → qualité maximale, coût absorbé par le prix du plan (250€/mois)

**Coût vs revenus** : Même pour le plan Freemium, le coût IA (~$0.20/mois) est négligeable et sert d'appel pour convertir vers les plans payants.

---

## Sécurité

1. **Stockage des photos** : répertoire non-public `storage/app/ai-captures/{org_id}/{date}/` avec URL signée pour accès
2. **Clé API** : uniquement dans `.env`, jamais exposée au mobile. Tous les appels IA passent par le backend
3. **Scoping organisation** : `AiVisionService` filtre TOUJOURS par `organization_id` — pas de fuite d'assets entre organisations
4. **Validation des uploads** : vérification MIME type (image/jpeg, image/png), taille, dimensions
5. **Contenu des photos** : le prompt LLM instruit d'ignorer le contenu d'arrière-plan et de se concentrer sur l'asset

---

## Application Mobile (React Native / Expo)

### Nouveaux packages nécessaires

```bash
npx expo install expo-image-manipulator   # Redimensionnement/compression photo
npx expo install expo-ml-kit              # OCR on-device (optionnel, pour offline)
```

### Nouveaux fichiers mobile

```
src/
  services/
    aiVisionService.ts           # Appels API endpoints IA
    offlineOcrService.ts         # OCR on-device (optionnel)
  screens/
    AiCaptureScreen.tsx          # Écran de capture photo pour IA
  components/
    AiResultsSheet.tsx           # Bottom sheet résultats IA
    AiMatchCard.tsx              # Carte de correspondance individuelle
    AiIdentificationHeader.tsx   # En-tête identification (catégorie, marque...)
    AiPendingBadge.tsx           # Badge photos en attente (offline)
    AiQuotaIndicator.tsx         # Indicateur de quota IA restant (quotidien/mensuel)
  hooks/
    useAiIdentify.ts             # Hook React pour le flux d'identification
  types/
    aiVision.ts                  # Types TypeScript pour les résultats IA
```

### Indicateur de quota IA (`AiQuotaIndicator.tsx`)

Affiche le quota IA restant pour l'organisation. Utilise les données de `GET /api/subscription/usage` :

```tsx
// Exemple d'affichage
// "IA : 3/5 aujourd'hui · 28/50 ce mois"
// Barre de progression colorée (vert → orange → rouge)
```

Cet indicateur est affiché :
- Sur l'écran de scan (en haut, discret)
- Avant d'ouvrir l'écran de capture IA
- Dans le bottom sheet des résultats IA (section `usage` de la réponse API)

### Intégration dans le flux de scan existant

```
Scan barcode/NFC
  │
  ├── Asset trouvé → Flux existant (marquer Found)
  │     │
  │     └── [Optionnel] Bouton "Vérifier par photo"
  │           → Vérifier quota IA (canMakeRequest)
  │           → AiCaptureScreen → POST /ai-verify
  │           → Affiche résultat vérification
  │
  └── "Asset inconnu" → Message d'erreur actuel
        │
        └── NOUVEAU : Bouton "Identifier par photo"
              → Vérifier quota IA (canMakeRequest)
              → Si quota épuisé : afficher message + lien upgrade
              → Sinon : AiCaptureScreen (vue caméra plein écran)
              → L'agent prend la photo
              → POST /api/tasks/{taskId}/ai-identify
              → AiResultsSheet s'affiche :
                  │
                  ├── Correspondance(s) trouvée(s) :
                  │     Liste de AiMatchCard (image, nom, code, confiance)
                  │     Badge "Attendu" si l'asset est dans la session
                  │     → Agent sélectionne → POST /ai-confirm (matched)
                  │     → Item marqué Found (identification_method = ai_vision)
                  │
                  ├── Pas de correspondance satisfaisante :
                  │     → "Ajouter comme inattendu"
                  │     → POST /ai-confirm (unexpected)
                  │
                  └── Annuler → POST /ai-confirm (dismissed)
```

### Gestion du quota épuisé côté mobile

Quand le quota IA est épuisé (réponse 403 du middleware `plan.limit`) :

```tsx
// Affichage dans le bottom sheet
{
  title: "Quota IA épuisé",
  message: "Votre plan Basic autorise 5 requêtes IA par jour.",
  actions: [
    { label: "Voir les plans", action: navigateToSubscription },
    { label: "Fermer", action: dismiss }
  ]
}
```

### Écran de capture photo (`AiCaptureScreen.tsx`)

- Vue caméra plein écran (réutilise `expo-camera`)
- Rectangle de guidage au centre pour cadrer l'objet
- Instruction : "Prenez une photo claire de l'objet"
- Bouton de capture (rond, en bas au centre)
- Après capture : prévisualisation avec "Utiliser cette photo" / "Reprendre"
- Bouton flash disponible
- Indicateur de chargement pendant l'analyse IA (1-4 secondes)
- **Indicateur de quota** en haut : "2/5 requêtes IA restantes aujourd'hui"

### Bottom sheet résultats IA (`AiResultsSheet.tsx`)

**Section haute — Identification :**
- Icône de la catégorie suggérée
- "Semble être : Dell Latitude 5540" + badge confiance (92%)
- Chips pour les textes détectés : `SN: ABCD-1234-EFGH`

**Section centrale — Correspondances :** (liste scrollable)
- Chaque `AiMatchCard` affiche :
  - Miniature de l'asset (depuis `primary_image_url`)
  - Nom, asset_code, emplacement
  - Barre de confiance (pourcentage + couleur)
  - Badge vert "Attendu" si dans la session courante
  - Badge orange "Déjà scanné" si déjà trouvé
  - Bouton "Sélectionner"

**Section basse — Actions :**
- "Confirmer la correspondance" (si un match est sélectionné)
- "Ajouter comme inattendu" (si aucun match ne convient)
- "Annuler"

**Footer — Quota IA :**
- "Requêtes restantes : 2/5 aujourd'hui · 22/50 ce mois" (données du champ `usage` de la réponse)

### Gestion offline

| Fonctionnalité | En ligne | Hors ligne |
|----------------|----------|-----------|
| Identification IA complète | ✅ | ❌ (nécessite Gemini/GPT-4o) |
| OCR on-device (lecture texte) | ✅ | ✅ (via ML Kit) |
| Résolution texte OCR → barcode_index | ✅ | ✅ |
| File d'attente photos | — | ✅ (stocke localement) |
| Sync photos en attente | ✅ (auto au retour réseau) | — |

**Mode dégradé offline** :
1. L'agent prend la photo → stockée localement
2. OCR on-device extrait les textes visibles (numéros de série, etc.)
3. Si un texte correspond à un code dans `barcode_index` SQLite → résolution immédiate
4. Sinon → photo mise en file d'attente avec badge "1 photo en attente d'identification"
5. Au retour du réseau → envoi automatique et affichage du résultat
6. **Si le quota IA est épuisé au moment du sync** → la photo reste en attente, notification "Quota IA insuffisant, X photos en attente"

### Table SQLite locale supplémentaire

```sql
CREATE TABLE pending_ai_photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id TEXT NOT NULL,
  photo_uri TEXT NOT NULL,           -- chemin local de la photo
  ocr_text TEXT,                     -- textes détectés en local (ML Kit)
  resolved_asset_id TEXT,            -- si OCR a résolu un asset
  status TEXT DEFAULT 'pending',     -- pending | uploading | completed | failed | quota_exceeded
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  synced_at TEXT
);
```

---

## Modification du payload Sync

Le `SyncController` doit accepter les nouveaux champs dans les scans :

```json
{
  "scans": [
    {
      "item_id": null,
      "asset_id": "01JN...",
      "status": "found",
      "scanned_at": "2026-02-21T14:30:00Z",
      "identification_method": "ai_vision",
      "ai_recognition_log_id": "01JN...",
      "ai_confidence": 0.89,
      "condition_notes": null
    }
  ]
}
```

---

## Widget Filament — Suivi des coûts IA

Ajouter un widget dans le dashboard App affichant l'utilisation IA relative au plan :

```php
// app/Filament/Widgets/AiVisionUsageWidget.php
class AiVisionUsageWidget extends Widget
{
    public function getUsageData(): array
    {
        $tenant = Filament::getTenant();
        $service = app(PlanLimitService::class);

        return [
            'daily' => $service->getUsageStats($tenant, PlanFeature::MaxAiRequestsDaily),
            'monthly' => $service->getUsageStats($tenant, PlanFeature::MaxAiRequestsMonthly),
            // Retourne : current, limit, percentage, is_unlimited, is_disabled, remaining, can_create
        ];
    }
}
```

Affichage :
- Nombre de requêtes IA aujourd'hui / limite du plan (barre de progression)
- Nombre de requêtes IA ce mois / limite du plan (barre de progression)
- Répartition Gemini Flash vs GPT-4o (fallback)
- Taux de succès (correspondances confirmées vs rejetées)

Fichier : `app/Filament/Widgets/AiVisionUsageWidget.php` (suit le pattern de `app/Filament/Widgets/PlanUsageWidget.php`)

---

## Phases d'implémentation

### Phase 1 : Backend — Fondations multi-provider (4-5 jours)
1. Installer `google-gemini-php/laravel` + `openai-php/laravel`
2. Créer `config/ai-vision.php` (configuration multi-provider, **sans** `daily_per_org`)
3. Créer les 2 migrations et les exécuter
4. Créer le modèle `AiRecognitionLog`
5. Créer `VisionProviderInterface` + `GeminiVisionProvider` + `OpenAiVisionProvider`
6. Créer les DTOs (`AiIdentificationResult`, `AiMatchResult`, `AiVerificationResult`)
7. Implémenter `AiVisionService` avec logique de fallback + **intégration `PlanLimitService`** + **double logging**
8. Créer `AiVisionController` avec endpoint `identify`
9. Ajouter les routes dans `routes/api.php` avec middleware `plan.limit:max_ai_requests_daily`
10. Tester avec Postman (vérifier Gemini Flash puis le fallback GPT-4o)

### Phase 2 : Backend — Vérification et confirmation (2-3 jours)
1. Implémenter `verifyAssetIdentity` dans `AiVisionService` (Cas 3)
2. Ajouter les endpoints `verify` et `confirm`
3. Modifier `InventoryScanService` pour accepter `identification_method`
4. Modifier `SyncController` pour les champs IA
5. Ajouter le rate limiter `ai-vision` (10 req/min, protection anti-abus)

### Phase 3 : Mobile — Capture photo (2-3 jours)
1. Créer `AiCaptureScreen` avec `expo-camera`
2. Compression d'image avec `expo-image-manipulator`
3. Créer `aiVisionService.ts` (appels API)
4. Intégrer le bouton "Identifier par photo" dans l'écran de scan
5. **Créer `AiQuotaIndicator.tsx`** (affichage quota restant)
6. **Gérer le cas quota épuisé** (message + lien upgrade)

### Phase 4 : Mobile — Interface résultats (2-3 jours)
1. Créer `AiResultsSheet` et `AiMatchCard`
2. Implémenter le flux confirmer/rejeter
3. Gérer les états de chargement et d'erreur
4. Feedback haptique et sonore
5. **Afficher le quota restant** dans le footer du bottom sheet

### Phase 5 : Offline et finitions (2-3 jours)
1. File d'attente photos offline (`pending_ai_photos` SQLite, avec statut `quota_exceeded`)
2. OCR on-device avec ML Kit (optionnel)
3. Badge "photos en attente" sur l'écran de scan
4. Sync automatique des photos au retour réseau (avec vérification quota)
5. Widget Filament `AiVisionUsageWidget` (utilisation IA relative au plan)

**Effort total estimé : 12-17 jours** pour un développeur.

---

## Vérification

### Backend (API IA + intégration souscription)
1. `composer require google-gemini-php/laravel openai-php/laravel` + `php artisan migrate`
2. Configurer `GEMINI_API_KEY` et `OPENAI_API_KEY` dans `.env`
3. Tester `POST /api/tasks/{id}/ai-identify` avec Postman (photo d'un laptop) → vérifier que Gemini Flash est utilisé (champ `provider` dans le log)
4. Vérifier que le log `ai_recognition_logs` est créé avec `provider=gemini`, `model=gemini-2.5-flash`
5. **Vérifier qu'une entrée `ai_usage_logs` est aussi créée** (pour le comptage quota)
6. Tester le fallback : envoyer une image ambiguë → vérifier que `used_fallback=true` et `provider=openai` dans le log
7. Tester `POST /api/tasks/{id}/ai-confirm` avec `action: matched`
8. Vérifier que l'`InventoryItem` est créé avec `identification_method = ai_vision`
9. **Tester le quota du plan** : sur un plan Freemium, envoyer 3 requêtes → vérifier le rejet 403 à la 3ème avec message "votre plan Freemium autorise 2 Requêtes IA / jour"
10. **Tester le quota mensuel** : vérifier qu'après 10 requêtes dans le mois (Freemium), les requêtes suivantes sont rejetées même si le quota quotidien est encore disponible
11. **Tester avec plan Premium** : vérifier que les requêtes passent sans limite
12. Tester `POST /api/tasks/{id}/ai-verify` avec un asset ayant une image
13. Vérifier `GET /api/subscription/usage` → les compteurs IA sont à jour

### Mobile
1. Scanner un barcode illisible → vérifier que "Identifier par photo" apparaît
2. Prendre une photo → vérifier l'envoi et l'affichage des résultats (1-4s)
3. **Vérifier l'indicateur de quota** : "2/5 requêtes IA restantes aujourd'hui"
4. Sélectionner une correspondance → vérifier que l'item est marqué Found
5. **Épuiser le quota quotidien** → vérifier le message "Quota IA épuisé" avec bouton "Voir les plans"
6. Passer en mode avion → prendre une photo → vérifier le stockage local
7. Réactiver le réseau → vérifier l'envoi automatique de la photo
8. Sur le web Filament → vérifier que l'item montre `identification_method = ai_vision`
9. **Sur le widget PlanUsageWidget** → vérifier que les barres IA quotidien/mensuel sont à jour
