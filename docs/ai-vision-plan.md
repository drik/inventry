# Reconnaissance d'Assets par IA (Vision AI)

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

## Choix technologique : OpenAI Vision (GPT-4o) ✓

### Pourquoi OpenAI Vision et pas Google Cloud Vision

| Critère | OpenAI Vision (GPT-4o) | Google Cloud Vision |
|---------|----------------------|-------------------|
| **Compréhension contextuelle** | Comprend le contexte métier (inventaire d'entreprise). Peut recevoir un prompt personnalisé avec les catégories de l'organisation | Retourne des labels génériques ("laptop", "electronics") sans contexte métier |
| **Comparaison d'images** | Envoie la photo + images de référence dans un seul appel, demande laquelle correspond | Pas de comparaison intégrée. Nécessite Vertex AI Matching Engine (infrastructure lourde) |
| **OCR + identification combinés** | Lit les numéros de série ET identifie l'objet dans le même appel | Nécessite des appels séparés (Label Detection + Text Detection + Logo Detection) |
| **Sortie structurée** | Supporte `response_format: json_schema` pour forcer un schéma JSON strict | Format de réponse fixe, nécessite transformation |
| **Coût estimé** | ~0.015$/requête (1 photo + 5 refs) → ~45$/mois pour 100 requêtes/jour | ~0.0045$/requête pour labels+OCR, MAIS infra Vertex AI pour matching : 50-100$/mois minimum |
| **Latence** | 2-4 secondes | 0.5-1s par feature, mais pas de matching natif |
| **Intégration Laravel** | Package `openai-php/laravel` mature | `google/cloud-vision` + service account plus verbeux |

**Verdict** : OpenAI Vision est le choix optimal pour ce cas d'usage. La capacité à comparer des images et comprendre le contexte métier dans un seul appel est décisive.

---

## Cas d'usage détaillés

### Cas 1 : Identifier un asset inconnu (`identify`)

**Déclencheur** : Le scan barcode retourne "Asset inconnu" ou l'agent ne trouve pas de code lisible.

**Flux** :
1. L'agent appuie sur "Identifier par photo"
2. L'écran de capture s'ouvre (vue caméra plein écran)
3. L'agent prend une photo de l'objet
4. La photo est envoyée au serveur → GPT-4o analyse
5. Résultat affiché : catégorie suggérée, marque/modèle détectés, textes lus (numéros de série)

### Cas 2 : Correspondance avec les assets connus (`match`)

**Combiné avec le Cas 1** dans un seul appel API.

**Flux** :
1. Le serveur sélectionne les assets candidats (même emplacement, avec image)
2. Envoie la photo capturée + jusqu'à 8 images de référence à GPT-4o
3. GPT-4o retourne les correspondances avec scores de confiance
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
composer require openai-php/laravel
php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"
```

### Configuration

**`.env`** :
```
OPENAI_API_KEY=sk-...
OPENAI_VISION_MODEL=gpt-4o
AI_VISION_ENABLED=true
AI_VISION_DAILY_LIMIT_PER_ORG=200
AI_VISION_MAX_IMAGE_SIZE_KB=2048
```

**Nouveau fichier `config/ai-vision.php`** :
```php
return [
    'enabled' => env('AI_VISION_ENABLED', false),
    'provider' => 'openai',
    'openai' => [
        'model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
        'max_tokens' => 1000,
    ],
    'limits' => [
        'daily_per_org' => (int) env('AI_VISION_DAILY_LIMIT_PER_ORG', 200),
        'max_image_size_kb' => (int) env('AI_VISION_MAX_IMAGE_SIZE_KB', 2048),
        'max_reference_images' => 8,
    ],
];
```

### Nouvelles routes API

```
POST  /api/tasks/{taskId}/ai-identify   → AiVisionController@identify   [auth:sanctum]
POST  /api/tasks/{taskId}/ai-verify     → AiVisionController@verify     [auth:sanctum]
POST  /api/tasks/{taskId}/ai-confirm    → AiVisionController@confirm    [auth:sanctum]
```

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
  }
}
```

**Response 429** (quota dépassé) :
```json
{ "message": "Quota quotidien d'identification IA atteint (200/200)." }
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
  "discrepancies": []
}
```

### Endpoint : `POST /api/tasks/{taskId}/ai-confirm`

L'utilisateur confirme ou rejette la suggestion IA.

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
    $table->json('ai_response');                      // réponse complète GPT-4o
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
| `config/ai-vision.php` | Configuration IA (modèle, limites, taille max) |
| `app/Services/AiVisionService.php` | Service principal : appels GPT-4o, sélection des candidats, prompt engineering |
| `app/Http/Controllers/Api/AiVisionController.php` | Contrôleur API (identify, verify, confirm) |
| `app/Models/AiRecognitionLog.php` | Modèle Eloquent pour le log des requêtes IA |
| `app/DTOs/AiIdentificationResult.php` | DTO : résultat d'identification |
| `app/DTOs/AiMatchResult.php` | DTO : résultat de correspondance |
| `app/DTOs/AiVerificationResult.php` | DTO : résultat de vérification |
| `database/migrations/xxxx_create_ai_recognition_logs_table.php` | Migration table de logs |
| `database/migrations/xxxx_add_ai_fields_to_inventory_items_table.php` | Migration colonnes IA |

## Fichiers backend à modifier

| Fichier | Modification |
|---------|-------------|
| `routes/api.php` | Ajouter les 3 routes `/ai-identify`, `/ai-verify`, `/ai-confirm` |
| `app/Services/InventoryScanService.php` | Ajouter paramètre `identification_method` aux méthodes `markAsFound()` et `addUnexpected()` |
| `app/Http/Controllers/Api/SyncController.php` | Accepter `identification_method`, `ai_recognition_log_id`, `ai_confidence` dans le payload sync |
| `app/Providers/AppServiceProvider.php` | Ajouter rate limiter `ai-vision` (10 req/min par org) |

---

## Service `AiVisionService` — Détail technique

### Méthodes publiques

```php
class AiVisionService
{
    // Cas 1+2 combinés : identifier + chercher des correspondances
    public function analyzePhoto(
        string $imagePath,
        string $organizationId,
        ?string $locationId = null,
        ?string $taskId = null
    ): AiAnalysisResult;

    // Cas 3 : vérifier qu'une photo correspond à un asset précis
    public function verifyAssetIdentity(
        string $capturedImagePath,
        Asset $asset
    ): AiVerificationResult;

    // Vérification du quota quotidien
    public function canMakeRequest(string $organizationId): bool;
}
```

### Stratégie de sélection des candidats (pour la comparaison d'images)

Avant d'envoyer à GPT-4o, le service doit sélectionner intelligemment les images de référence :

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

### Prompt GPT-4o

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

1. **Rate limiter Laravel** (par minute) :
```php
// AppServiceProvider::boot()
RateLimiter::for('ai-vision', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()->organization_id);
});
```

2. **Quota quotidien** (par organisation) :
```php
// AiVisionService::canMakeRequest()
$todayCount = AiRecognitionLog::where('organization_id', $orgId)
    ->whereDate('created_at', today())->count();
return $todayCount < config('ai-vision.limits.daily_per_org');
```

3. **Validation de taille** : rejet des images > `max_image_size_kb` (2 Mo par défaut)

### Estimation des coûts

| Volume | Coût mensuel estimé |
|--------|-------------------|
| 10 requêtes/jour | ~4.50 $ |
| 50 requêtes/jour | ~22.50 $ |
| 100 requêtes/jour | ~45.00 $ |
| 200 requêtes/jour | ~90.00 $ |

(Basé sur ~0.015$/requête avec 1 photo capturée + 5 images de référence)

---

## Sécurité

1. **Stockage des photos** : répertoire non-public `storage/app/ai-captures/{org_id}/{date}/` avec URL signée pour accès
2. **Clé API** : uniquement dans `.env`, jamais exposée au mobile. Tous les appels IA passent par le backend
3. **Scoping organisation** : `AiVisionService` filtre TOUJOURS par `organization_id` — pas de fuite d'assets entre organisations
4. **Validation des uploads** : vérification MIME type (image/jpeg, image/png), taille, dimensions
5. **Contenu des photos** : le prompt GPT-4o instruit d'ignorer le contenu d'arrière-plan et de se concentrer sur l'asset

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
  hooks/
    useAiIdentify.ts             # Hook React pour le flux d'identification
  types/
    aiVision.ts                  # Types TypeScript pour les résultats IA
```

### Intégration dans le flux de scan existant

```
Scan barcode/NFC
  │
  ├── Asset trouvé → Flux existant (marquer Found)
  │     │
  │     └── [Optionnel] Bouton "Vérifier par photo"
  │           → AiCaptureScreen → POST /ai-verify
  │           → Affiche résultat vérification
  │
  └── "Asset inconnu" → Message d'erreur actuel
        │
        └── NOUVEAU : Bouton "Identifier par photo"
              → AiCaptureScreen (vue caméra plein écran)
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

### Écran de capture photo (`AiCaptureScreen.tsx`)

- Vue caméra plein écran (réutilise `expo-camera`)
- Rectangle de guidage au centre pour cadrer l'objet
- Instruction : "Prenez une photo claire de l'objet"
- Bouton de capture (rond, en bas au centre)
- Après capture : prévisualisation avec "Utiliser cette photo" / "Reprendre"
- Bouton flash disponible
- Indicateur de chargement pendant l'analyse IA (2-4 secondes)

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

### Gestion offline

| Fonctionnalité | En ligne | Hors ligne |
|----------------|----------|-----------|
| Identification IA complète | ✅ | ❌ (nécessite GPT-4o) |
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

### Table SQLite locale supplémentaire

```sql
CREATE TABLE pending_ai_photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id TEXT NOT NULL,
  photo_uri TEXT NOT NULL,           -- chemin local de la photo
  ocr_text TEXT,                     -- textes détectés en local (ML Kit)
  resolved_asset_id TEXT,            -- si OCR a résolu un asset
  status TEXT DEFAULT 'pending',     -- pending | uploading | completed | failed
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

## Widget Filament — Suivi des coûts IA (optionnel)

Ajouter un widget dans le dashboard admin affichant :
- Nombre de requêtes IA aujourd'hui / quota
- Coût estimé du mois en cours
- Taux de succès (correspondances confirmées vs rejetées)
- Top catégories identifiées

Fichier : `app/Filament/Widgets/AiVisionUsageWidget.php` (suit le pattern des widgets existants dans `app/Filament/Widgets/`)

---

## Phases d'implémentation

### Phase 1 : Backend — Fondations (3-4 jours)
1. Installer `openai-php/laravel`
2. Créer `config/ai-vision.php`
3. Créer les 2 migrations et les exécuter
4. Créer le modèle `AiRecognitionLog`
5. Créer les DTOs (`AiIdentificationResult`, `AiMatchResult`, `AiVerificationResult`)
6. Implémenter `AiVisionService` (méthode `analyzePhoto` — Cas 1+2)
7. Créer `AiVisionController` avec endpoint `identify`
8. Ajouter les routes dans `routes/api.php`
9. Tester avec Postman

### Phase 2 : Backend — Vérification et confirmation (2-3 jours)
1. Implémenter `verifyAssetIdentity` dans `AiVisionService` (Cas 3)
2. Ajouter les endpoints `verify` et `confirm`
3. Modifier `InventoryScanService` pour accepter `identification_method`
4. Modifier `SyncController` pour les champs IA
5. Ajouter le rate limiter

### Phase 3 : Mobile — Capture photo (2-3 jours)
1. Créer `AiCaptureScreen` avec `expo-camera`
2. Compression d'image avec `expo-image-manipulator`
3. Créer `aiVisionService.ts` (appels API)
4. Intégrer le bouton "Identifier par photo" dans l'écran de scan

### Phase 4 : Mobile — Interface résultats (2-3 jours)
1. Créer `AiResultsSheet` et `AiMatchCard`
2. Implémenter le flux confirmer/rejeter
3. Gérer les états de chargement et d'erreur
4. Feedback haptique et sonore

### Phase 5 : Offline et finitions (2-3 jours)
1. File d'attente photos offline (`pending_ai_photos` SQLite)
2. OCR on-device avec ML Kit (optionnel)
3. Badge "photos en attente" sur l'écran de scan
4. Sync automatique des photos au retour réseau
5. Widget Filament suivi coûts IA (optionnel)

**Effort total estimé : 11-16 jours** pour un développeur.

---

## Vérification

### Backend (API IA)
1. `composer require openai-php/laravel` + `php artisan migrate`
2. Configurer `OPENAI_API_KEY` dans `.env`
3. Tester `POST /api/tasks/{id}/ai-identify` avec Postman (photo d'un laptop)
4. Vérifier que le log `ai_recognition_logs` est créé
5. Tester `POST /api/tasks/{id}/ai-confirm` avec `action: matched`
6. Vérifier que l'`InventoryItem` est créé avec `identification_method = ai_vision`
7. Tester le quota : envoyer 201 requêtes → vérifier le rejet 429
8. Tester `POST /api/tasks/{id}/ai-verify` avec un asset ayant une image

### Mobile
1. Scanner un barcode illisible → vérifier que "Identifier par photo" apparaît
2. Prendre une photo → vérifier l'envoi et l'affichage des résultats (2-4s)
3. Sélectionner une correspondance → vérifier que l'item est marqué Found
4. Passer en mode avion → prendre une photo → vérifier le stockage local
5. Réactiver le réseau → vérifier l'envoi automatique de la photo
6. Sur le web Filament → vérifier que l'item montre `identification_method = ai_vision`
