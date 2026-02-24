# Plan : Notes IA, Conditions, Medias S3, Rapports d'inventaire

## Contexte

L'application d'inventaire a besoin d'enrichir le workflow de scan avec : des notes assistées par IA (reformulation, description photo, transcription audio, description vidéo), un attribut condition personnalisable par org, le changement de statut manuel, des pièces jointes médias (photos/audio/vidéo) sur items et tâches, la gestion documentaire sur assets, le stockage S3 facturé aux clients, et la génération de rapports d'inventaire (PDF + Excel + Web).

---

## Phase 1 : Fondations (Storage S3 + Media polymorphique + Conditions)

### 1.1 Migration vers S3

**Nouveau fichier** : `config/media.php`
```php
return [
    'disk' => env('MEDIA_DISK', 's3'),        // s3 en prod, public en dev
    'max_upload_size_mb' => 50,                 // limite par fichier
    'audio_max_duration_sec' => 120,
    'video_max_duration_sec' => 30,
    'allowed_document_mimes' => ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'],
    'image_mimes' => ['jpg','jpeg','png','webp'],
    'audio_mimes' => ['mp3','wav','m4a','ogg','webm'],
    'video_mimes' => ['mp4','mov','webm'],
    'storage_quotas_mb' => [                    // quotas par défaut par plan
        'freemium' => 500,
        'basic' => 5120,
        'pro' => 20480,
        'premium' => 51200,
    ],
    'overage_price_per_gb' => 1.00,             // $1/Go supplémentaire
];
```

**Modifier** : `config/filesystems.php` — vérifier que le disk `s3` est correctement configuré (déjà présent).

### 1.2 Table `media` (polymorphique)

**Migration** : `create_media_table.php`
```
media
├── id (ulid)
├── organization_id (fk)
├── mediable_type (string) — App\Models\InventoryItem, InventoryTask, Asset
├── mediable_id (ulid)
├── collection (string) — 'photos', 'audio', 'video', 'documents', 'notes'
├── disk (string) — 's3' ou 'public'
├── file_path (string)
├── file_name (string) — nom original
├── mime_type (string)
├── size_bytes (unsigned bigint)
├── metadata (json, nullable) — {duration_sec, width, height, ai_description, etc.}
├── uploaded_by (fk users)
├── created_at, updated_at
└── index(organization_id), index(mediable_type, mediable_id)
```

**Nouveau modèle** : `app/Models/Media.php`
- Trait : `HasUlids`, `BelongsToOrganization`
- Relations : `mediable()` morphTo, `uploader()` belongsTo User
- Accessors : `url` (génère URL signée S3 ou URL publique), `human_size`
- Scopes : `photos()`, `audio()`, `video()`, `documents()`

**Trait** : `app/Models/Concerns/HasMedia.php`
```php
trait HasMedia {
    public function media() { return $this->morphMany(Media::class, 'mediable'); }
    public function photos() { return $this->media()->where('collection', 'photos'); }
    public function audioFiles() { return $this->media()->where('collection', 'audio'); }
    public function videos() { return $this->media()->where('collection', 'video'); }
    public function documents() { return $this->media()->where('collection', 'documents'); }
}
```

**Ajouter HasMedia à** : `InventoryItem`, `InventoryTask`, `Asset`

### 1.3 Table `asset_conditions` (personnalisable par org)

**Migration** : `create_asset_conditions_table.php`
```
asset_conditions
├── id (ulid)
├── organization_id (fk)
├── name (string) — "Bon état", "Endommagé", etc.
├── slug (string)
├── description (text, nullable)
├── color (string, nullable) — "#22c55e", "#ef4444"
├── icon (string, nullable) — "heroicon-o-check-circle"
├── sort_order (unsigned int, default 0)
├── is_default (boolean, default false) — créé automatiquement
├── created_at, updated_at
└── unique(organization_id, slug)
```

**Conditions par défaut** (créées à l'inscription org, comme les AssetTags) :
| Nom | Slug | Couleur | Icône |
|-----|------|---------|-------|
| Neuf | new | #3b82f6 | heroicon-o-sparkles |
| Bon état | good | #22c55e | heroicon-o-check-circle |
| Usé | worn | #f59e0b | heroicon-o-minus-circle |
| Endommagé | damaged | #ef4444 | heroicon-o-exclamation-triangle |
| Non fonctionnel | non_functional | #dc2626 | heroicon-o-x-circle |
| Hors service | out_of_service | #6b7280 | heroicon-o-no-symbol |

**Modèle** : `app/Models/AssetCondition.php`
- Relations : `organization()`, `inventoryItems()`
- Scope global organisation (même pattern que Manufacturer : null = global, org_id = tenant)

**Migration** : `add_condition_id_to_inventory_items_table.php`
- Ajouter `condition_id` (ulid, nullable, FK asset_conditions) à `inventory_items`

**Modifier** `Organization::booted()` (creating callback) :
- Après création des AssetTags par défaut, créer les 6 conditions par défaut

### 1.4 Table `storage_usages` (tracking par org)

**Migration** : `create_storage_usages_table.php`
```
storage_usages
├── id (ulid)
├── organization_id (fk, unique)
├── used_bytes (unsigned bigint, default 0)
├── updated_at
```

**Modèle** : `app/Models/StorageUsage.php`

### 1.5 StorageService

**Nouveau** : `app/Services/StorageService.php`
```php
class StorageService {
    public function upload(Organization $org, UploadedFile $file, string $collection, Model $mediable): Media
    // - Vérifie quota (canUpload)
    // - Stocke sur le disk configuré (s3 ou public)
    // - Path: {org_id}/{collection}/{date}/{uuid}.{ext}
    // - Crée le Media record
    // - Met à jour storage_usages.used_bytes (+= size)

    public function delete(Media $media): void
    // - Supprime du disk
    // - Met à jour storage_usages.used_bytes (-= size)

    public function canUpload(Organization $org, int $fileSizeBytes): bool
    // - Compare used_bytes + fileSizeBytes vs quota du plan

    public function getUsageStats(Organization $org): array
    // - Returns: used_bytes, quota_bytes, percentage, remaining_bytes, overage_bytes

    public function getSignedUrl(Media $media, int $expirationMinutes = 60): string
    // - URL signée S3 pour accès temporaire

    public function recalculateUsage(Organization $org): void
    // - Recalcule used_bytes depuis la table media (maintenance)
}
```

### 1.6 PlanFeature : MaxStorageMb

**Modifier** `app/Enums/PlanFeature.php` :
- Ajouter `MaxStorageMb = 'max_storage_mb'`
- Mettre à jour `isNumericLimit()`, `getLabel()`

**Modifier** `app/Services/PlanLimitService.php` :
- Ajouter case `MaxStorageMb` dans `getCurrentUsage()` → query `storage_usages.used_bytes / 1048576`

### 1.7 Changement de statut manuel des items

**Migration** : `create_inventory_item_status_changes_table.php`
```
inventory_item_status_changes
├── id (ulid)
├── organization_id (fk)
├── inventory_item_id (fk)
├── from_status (string)
├── to_status (string)
├── changed_by (fk users)
├── reason (text, nullable)
├── created_at
```

**Modifier** `InventoryScanService` :
- Nouvelle méthode `changeItemStatus(InventoryItem $item, InventoryItemStatus $newStatus, string $userId, ?string $reason): void`
  - Crée un enregistrement dans `inventory_item_status_changes`
  - Met à jour `item->status`
  - Rafraîchit les compteurs session

---

## Phase 2 : API Mobile — Medias, Notes, Conditions, Statut

### 2.1 MediaController (API)

**Nouveau** : `app/Http/Controllers/Api/MediaController.php`

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `POST /api/tasks/{taskId}/items/{itemId}/media` | upload | Ajouter photo/audio/vidéo à un item |
| `POST /api/tasks/{taskId}/media` | upload | Ajouter photo/audio/vidéo à la tâche |
| `GET /api/media/{mediaId}` | show | Récupérer URL signée |
| `DELETE /api/media/{mediaId}` | destroy | Supprimer un média |

**Upload request** :
```
Content-Type: multipart/form-data
- file (required) : le fichier
- collection (required) : 'photos' | 'audio' | 'video'
```

**Upload response** :
```json
{
  "media": {
    "id": "01HX...",
    "collection": "photos",
    "file_name": "photo_001.jpg",
    "mime_type": "image/jpeg",
    "size_bytes": 245000,
    "url": "https://s3.../signed-url",
    "metadata": {}
  },
  "storage": {
    "used_bytes": 52428800,
    "quota_bytes": 524288000,
    "percentage": 10
  }
}
```

### 2.2 Conditions API

**Modifier** `TaskController` :

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `GET /api/conditions` | index | Liste des conditions de l'org |
| `PUT /api/tasks/{taskId}/items/{itemId}/condition` | update | Changer la condition d'un item |

**Modifier** `TaskController::download()` :
- Ajouter `conditions` dans la réponse (toutes les conditions de l'org)
- Ajouter `condition_id` et `condition_name` dans chaque item

### 2.3 Changement de statut API

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `PUT /api/tasks/{taskId}/items/{itemId}/status` | update | Changer le statut de scan d'un item |

**Request** : `{ "status": "found", "reason": "Trouvé dans un autre bureau" }`
**Response** : item mis à jour + nouveau statut

### 2.4 Notes sur items et tâches (texte simple)

**Migration** : `create_inventory_notes_table.php`
```
inventory_notes
├── id (ulid)
├── organization_id (fk)
├── notable_type (string) — InventoryItem ou InventoryTask
├── notable_id (ulid)
├── content (text) — le texte de la note
├── original_content (text, nullable) — texte avant reformulation IA
├── source_type (string) — 'text', 'ai_rephrase', 'ai_photo_desc', 'ai_audio_transcript', 'ai_video_desc'
├── source_media_id (fk media, nullable) — lien vers le média source (photo/audio/vidéo)
├── ai_usage_log_id (fk, nullable)
├── created_by (fk users)
├── created_at, updated_at
└── index(notable_type, notable_id)
```

**Modèle** : `app/Models/InventoryNote.php` (morphMany via `notable`)

**Trait** : `app/Models/Concerns/HasNotes.php`
```php
trait HasNotes {
    public function notes() { return $this->morphMany(InventoryNote::class, 'notable'); }
}
```
Ajouter à `InventoryItem` et `InventoryTask`.

### 2.5 Notes API

**Nouveau** : `app/Http/Controllers/Api/NoteController.php`

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `POST /api/tasks/{taskId}/items/{itemId}/notes` | store | Ajouter une note à un item |
| `POST /api/tasks/{taskId}/notes` | store | Ajouter une note à la tâche |
| `GET /api/tasks/{taskId}/items/{itemId}/notes` | index | Lister les notes d'un item |
| `GET /api/tasks/{taskId}/notes` | index | Lister les notes de la tâche |
| `DELETE /api/notes/{noteId}` | destroy | Supprimer une note |

**Request (texte simple)** :
```json
{ "content": "L'écran présente une fissure en bas à gauche" }
```

---

## Phase 3 : AI Assistant Service (Reformulation, Description, Transcription)

### 3.1 Architecture Provider généralisée

**Renommer/Abstraire** le provider interface :

**Nouveau** : `app/Services/AiProviders/AiProviderInterface.php`
```php
interface AiProviderInterface {
    public function generateText(string $prompt, array $options = []): AiProviderResponse;
    public function analyzeImage(array $images, string $prompt, array $options = []): AiProviderResponse;
    public function transcribeAudio(string $audioPath, string $prompt, array $options = []): AiProviderResponse;
    public function analyzeVideo(string $videoPath, string $prompt, array $options = []): AiProviderResponse;
}
```

**Nouveau DTO** : `app/DTOs/AiProviderResponse.php`
```php
class AiProviderResponse {
    public string $text;
    public int $promptTokens;
    public int $completionTokens;
    public float $estimatedCostUsd;
}
```

**Nouveau** : `app/Services/AiProviders/GeminiProvider.php`
- Implémente toutes les méthodes (Gemini 2.5 Flash est nativement multimodal : texte, image, audio, vidéo)
- Réutilise la connexion Gemini existante

**Nouveau** : `app/Services/AiProviders/OpenAiProvider.php`
- `generateText()` : GPT-4o
- `analyzeImage()` : GPT-4o vision
- `transcribeAudio()` : Whisper API
- `analyzeVideo()` : GPT-4o (extrait frames + analyse)

**Note** : `AiVisionService` continue d'utiliser les providers existants (`GeminiVisionProvider`, `OpenAiVisionProvider`). Les nouveaux providers sont pour le service assistant. Migration progressive possible plus tard.

### 3.2 AiAssistantService

**Nouveau** : `app/Services/AiAssistantService.php`
```php
class AiAssistantService {
    // Provider selection (même logique que AiVisionService)
    protected function getProvider(Organization $org): AiProviderInterface
    protected function getFallbackProvider(Organization $org): ?AiProviderInterface

    // 4 modes d'assistance
    public function rephraseText(string $text, Organization $org, string $context = 'inventory'): AiAssistantResult
    public function describePhoto(string $imagePath, Organization $org): AiAssistantResult
    public function transcribeAudio(string $audioPath, Organization $org): AiAssistantResult
    public function describeVideo(string $videoPath, Organization $org): AiAssistantResult

    // Utilitaires
    public function canMakeRequest(Organization $org): bool // réutilise PlanLimitService
    protected function logUsage(Organization $org, string $userId, int $tokens): void
}
```

**Nouveau DTO** : `app/DTOs/AiAssistantResult.php`
```php
class AiAssistantResult {
    public string $text;           // résultat
    public string $provider;       // 'gemini' ou 'openai'
    public bool $usedFallback;
    public int $promptTokens;
    public int $completionTokens;
    public float $estimatedCostUsd;
}
```

### 3.3 Prompts IA

**Reformulation** :
```
Tu es un assistant d'inventaire professionnel. Reformule la note suivante de manière
claire, concise et professionnelle, en conservant toutes les informations factuelles.
Contexte : note d'observation lors d'un inventaire d'actifs.
Note originale : "{text}"
Réponds uniquement avec la note reformulée, sans explication.
```

**Description photo** :
```
Tu es un assistant d'inventaire. Décris de manière factuelle et concise ce que tu vois
sur cette photo prise lors d'un inventaire d'actifs. Concentre-toi sur :
- L'état physique de l'objet (dommages, usure, propreté)
- Les détails identifiants visibles (marque, modèle, étiquettes, numéros de série)
- L'environnement immédiat si pertinent
Réponds en français, 2-4 phrases maximum.
```

**Transcription audio** :
```
Transcris fidèlement cet enregistrement audio. C'est une note vocale d'un agent
d'inventaire décrivant l'état d'un actif. Corrige les hésitations mineures mais
conserve le sens exact. Réponds uniquement avec la transcription.
```

**Description vidéo** :
```
Tu es un assistant d'inventaire. Décris ce que tu observes dans cette courte vidéo
prise lors d'un inventaire d'actifs. Concentre-toi sur :
- L'objet filmé et son état
- Tout défaut ou dommage visible
- Les mouvements ou démonstrations (ex: l'agent montre un dysfonctionnement)
Réponds en français, 3-5 phrases maximum.
```

### 3.4 API pour l'assistance IA sur les notes

**Nouveau** : `app/Http/Controllers/Api/AiAssistantController.php`

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `POST /api/tasks/{taskId}/ai-rephrase` | rephrase | Reformuler un texte |
| `POST /api/tasks/{taskId}/ai-describe-photo` | describePhoto | Décrire une photo |
| `POST /api/tasks/{taskId}/ai-transcribe` | transcribe | Transcrire un audio |
| `POST /api/tasks/{taskId}/ai-describe-video` | describeVideo | Décrire une vidéo |

Tous ces endpoints :
- Vérifient le quota IA (daily + monthly)
- Comptent comme des requêtes IA (AiUsageLog)
- Middleware : `throttle:ai-vision`, `plan.limit:max_ai_requests_daily`

**Request rephrase** : `{ "text": "le pc il est cassé l'écran" }`
**Response** : `{ "text": "L'écran de l'ordinateur portable est endommagé.", "usage": {...} }`

**Request describe-photo** : `multipart/form-data` avec `photo` (file)
**Response** : `{ "description": "Un ordinateur portable Dell...", "usage": {...} }`

**Request transcribe** : `multipart/form-data` avec `audio` (file)
**Response** : `{ "transcription": "L'imprimante ne s'allume plus...", "usage": {...} }`

**Request describe-video** : `multipart/form-data` avec `video` (file)
**Response** : `{ "description": "La vidéo montre un agent...", "usage": {...} }`

### 3.5 Workflow mobile pour créer une note IA

1. L'agent choisit le mode (texte, photo, audio, vidéo)
2. L'agent capture/enregistre le contenu
3. Le mobile uploade le fichier via `POST /api/tasks/{taskId}/items/{itemId}/media`
4. Le mobile appelle l'endpoint IA correspondant (ex: `POST /api/tasks/{taskId}/ai-describe-photo`)
5. L'IA retourne le texte généré
6. L'agent peut éditer le texte si besoin
7. Le mobile crée la note via `POST /api/tasks/{taskId}/items/{itemId}/notes` avec :
   ```json
   {
     "content": "Texte final (édité ou non)",
     "source_type": "ai_photo_desc",
     "source_media_id": "01HX..."
   }
   ```

---

## Phase 4 : Gestion documentaire sur Asset

### 4.1 API Documents

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `POST /api/assets/{assetId}/documents` | upload | Ajouter un document à un asset |
| `GET /api/assets/{assetId}/documents` | index | Lister les documents d'un asset |
| `GET /api/media/{mediaId}/download` | download | Télécharger (URL signée) |
| `DELETE /api/media/{mediaId}` | destroy | Supprimer |

### 4.2 Filament UI pour documents

**Modifier** : `app/Filament/App/Resources/AssetResource.php`
- Ajouter une tab "Documents" sur la page View
- Composant Filament FileUpload pointant vers le disk S3
- Liste des documents avec nom, type, taille, date, téléchargement

---

## Phase 5 : Rapports d'inventaire

### 5.1 Modèle de rapport

**Migration** : `create_inventory_reports_table.php`
```
inventory_reports
├── id (ulid)
├── organization_id (fk)
├── session_id (fk inventory_sessions)
├── task_id (fk inventory_tasks, nullable) — null = rapport de session
├── type (string) — 'task_report', 'session_report'
├── title (string)
├── summary (text, nullable) — résumé rédigé (IA ou manuel)
├── ai_summary (text, nullable) — résumé brut généré par l'IA
├── data (json) — snapshot des statistiques au moment de la génération
├── generated_by (fk users)
├── pdf_media_id (fk media, nullable) — lien vers le PDF généré
├── excel_media_id (fk media, nullable) — lien vers l'Excel généré
├── created_at, updated_at
```

**Modèle** : `app/Models/InventoryReport.php`

### 5.2 ReportService

**Nouveau** : `app/Services/InventoryReportService.php`
```php
class InventoryReportService {
    public function generateTaskReport(InventoryTask $task, string $userId): InventoryReport
    // - Collecte stats du task (items scannés, manquants, inattendus, conditions)
    // - Collecte notes de l'agent
    // - Optionnel : appel IA pour générer un résumé
    // - Sauvegarde le rapport

    public function generateSessionReport(InventorySession $session, string $userId): InventoryReport
    // - Agrège les rapports de toutes les tâches
    // - Stats globales
    // - IA consolide les résumés de tâches en un rapport global
    // - Sauvegarde

    public function generatePdf(InventoryReport $report): Media
    // - Génère un PDF (barryvdh/laravel-dompdf)
    // - Stocke sur S3 via StorageService
    // - Retourne le Media

    public function generateExcel(InventoryReport $report): Media
    // - Génère un Excel (maatwebsite/excel)
    // - Stocke sur S3 via StorageService
    // - Retourne le Media

    public function aiGenerateSummary(InventoryReport $report, Organization $org): string
    // - Utilise AiAssistantService::rephraseText() ou generateText()
    // - Prompt spécifique pour les rapports d'inventaire
}
```

### 5.3 Contenu du rapport

**Rapport de tâche** :
```
1. En-tête : org logo, nom session, nom tâche, lieu, agent, dates
2. Résumé : paragraphe (IA ou manuel)
3. Statistiques :
   - Total attendus / scannés / trouvés / manquants / inattendus
   - Répartition par condition (graphique camembert)
   - Taux de complétion
4. Liste détaillée des items :
   - Nom, code, statut, condition, méthode d'identification, notes
5. Items problématiques (manquants + endommagés) en surbrillance
6. Notes de l'agent
7. Photos jointes (thumbnails)
```

**Rapport de session** :
```
1. En-tête : org logo, nom session, dates, créateur
2. Résumé consolidé (IA)
3. Statistiques globales
4. Résumé par tâche/lieu (tableau)
5. Items critiques consolidés (tous lieux)
6. Recommandations (IA-assisted)
```

### 5.4 Packages à installer

```bash
composer require barryvdh/laravel-dompdf
composer require maatwebsite/excel
```

### 5.5 Vues PDF (Blade)

**Nouveau** : `resources/views/reports/task-report.blade.php`
**Nouveau** : `resources/views/reports/session-report.blade.php`

### 5.6 Exports Excel

**Nouveau** : `app/Exports/TaskReportExport.php`
**Nouveau** : `app/Exports/SessionReportExport.php`

### 5.7 Filament UI

**Modifier** : `ViewInventorySession` page
- Header action : "Générer le rapport" (crée le rapport)
- Header action : "Voir le rapport" (lien vers la page rapport)

**Nouvelle page** : `app/Filament/App/Resources/InventorySessionResource/Pages/ViewReport.php`
- Affiche le rapport web (stats, résumé, items, notes)
- Actions : "Télécharger PDF", "Télécharger Excel", "Régénérer le résumé IA"

### 5.8 API Rapports

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `POST /api/tasks/{taskId}/report` | generate | Générer un rapport de tâche |
| `GET /api/tasks/{taskId}/report` | show | Voir le rapport de tâche |
| `GET /api/sessions/{sessionId}/report` | show | Voir le rapport de session |
| `GET /api/reports/{reportId}/pdf` | pdf | Télécharger le PDF |
| `GET /api/reports/{reportId}/excel` | excel | Télécharger l'Excel |

---

## Phase 6 : Mise à jour du Download endpoint

**Modifier** `TaskController::download()` — enrichir avec toutes les nouvelles données :
```json
{
  "task": { "...", "notes": [...] },
  "items": [{
    "...",
    "condition_id": "01HX...",
    "condition_name": "Bon état",
    "media": [{"id": "...", "collection": "photos", "url": "..."}],
    "notes": [{"id": "...", "content": "...", "source_type": "text"}]
  }],
  "conditions": [
    {"id": "01HX...", "name": "Bon état", "slug": "good", "color": "#22c55e"}
  ],
  "storage": { "used_bytes": 52428800, "quota_bytes": 524288000 }
}
```

---

## Ordre d'implémentation recommandé

| Étape | Description | Fichiers principaux |
|-------|-------------|-------------------|
| **1** | Migration `media` + modèle Media + trait HasMedia + StorageService | Migration, Model, Service |
| **2** | Migration `asset_conditions` + modèle + conditions par défaut dans Organization | Migration, Model, Organization.php |
| **3** | Migration `add_condition_id` à inventory_items + migration `inventory_item_status_changes` | Migrations, InventoryItem.php |
| **4** | PlanFeature::MaxStorageMb + PlanLimitService update | Enum, Service |
| **5** | Migration `inventory_notes` + modèle + trait HasNotes | Migration, Model, Trait |
| **6** | API : MediaController (upload/delete sur items et tâches) | Controller, Routes |
| **7** | API : Conditions (list, update condition sur item) | Controller, Routes |
| **8** | API : Changement statut manuel + audit trail | Controller, InventoryScanService |
| **9** | API : NoteController (CRUD notes) | Controller, Routes |
| **10** | Providers IA généralisés (GeminiProvider, OpenAiProvider) | Services/AiProviders/ |
| **11** | AiAssistantService + 4 endpoints IA (rephrase, describe, transcribe, video) | Service, Controller, Routes |
| **12** | Gestion documentaire Asset (API + Filament UI) | Controller, AssetResource |
| **13** | Migration `inventory_reports` + InventoryReportService | Migration, Model, Service |
| **14** | Installer dompdf + excel + vues Blade PDF | Composer, Views |
| **15** | Filament : page ViewReport + actions sur ViewSession | Pages Filament |
| **16** | API Rapports (generate, show, pdf, excel) | Controller, Routes |
| **17** | Enrichir download endpoint avec conditions, notes, media | TaskController |
| **18** | Mise à jour docs/API.md et postman_collection.json | Docs |

---

## Fichiers existants clés à modifier

| Fichier | Modifications |
|---------|---------------|
| `app/Enums/PlanFeature.php` | + MaxStorageMb |
| `app/Services/PlanLimitService.php` | + case MaxStorageMb |
| `app/Models/Organization.php` | + création conditions par défaut |
| `app/Models/InventoryItem.php` | + HasMedia, HasNotes, + condition(), + statusChanges() |
| `app/Models/InventoryTask.php` | + HasMedia, HasNotes |
| `app/Models/Asset.php` | + HasMedia (documents) |
| `app/Services/InventoryScanService.php` | + changeItemStatus() |
| `app/Http/Controllers/Api/TaskController.php` | + conditions dans download, + endpoints statut/condition |
| `routes/api.php` | + tous les nouveaux endpoints |
| `app/Filament/App/Resources/AssetResource.php` | + tab Documents |
| `app/Filament/App/Resources/InventorySessionResource/Pages/ViewInventorySession.php` | + actions rapport |

## Nouveaux fichiers à créer

| Fichier | Description |
|---------|-------------|
| `config/media.php` | Configuration storage/media |
| `app/Models/Media.php` | Modèle média polymorphique |
| `app/Models/AssetCondition.php` | Modèle condition |
| `app/Models/InventoryNote.php` | Modèle note |
| `app/Models/InventoryReport.php` | Modèle rapport |
| `app/Models/StorageUsage.php` | Tracking storage par org |
| `app/Models/InventoryItemStatusChange.php` | Audit statut |
| `app/Models/Concerns/HasMedia.php` | Trait morphMany media |
| `app/Models/Concerns/HasNotes.php` | Trait morphMany notes |
| `app/Services/StorageService.php` | Upload/delete/quota S3 |
| `app/Services/AiAssistantService.php` | 4 modes IA |
| `app/Services/AiProviders/AiProviderInterface.php` | Interface générique |
| `app/Services/AiProviders/GeminiProvider.php` | Provider Gemini multimodal |
| `app/Services/AiProviders/OpenAiProvider.php` | Provider OpenAI + Whisper |
| `app/Services/InventoryReportService.php` | Génération rapports |
| `app/DTOs/AiProviderResponse.php` | Réponse provider générique |
| `app/DTOs/AiAssistantResult.php` | Résultat assistant IA |
| `app/Http/Controllers/Api/MediaController.php` | API médias |
| `app/Http/Controllers/Api/NoteController.php` | API notes |
| `app/Http/Controllers/Api/AiAssistantController.php` | API assistant IA |
| `app/Http/Controllers/Api/ReportController.php` | API rapports |
| `app/Exports/TaskReportExport.php` | Export Excel tâche |
| `app/Exports/SessionReportExport.php` | Export Excel session |
| `resources/views/reports/task-report.blade.php` | Vue PDF tâche |
| `resources/views/reports/session-report.blade.php` | Vue PDF session |
| 8 migrations | Tables + colonnes |

---

## Vérification

1. **Storage** : Upload une image sur un item via API → vérifier stockage S3 + tracking usage
2. **Conditions** : Créer org → vérifier 6 conditions par défaut → assigner condition via API
3. **Statut** : Changer statut item via API → vérifier audit trail + compteurs session
4. **Notes** : Créer note texte sur item → reformuler via IA → vérifier les deux versions
5. **AI** : Upload audio → transcrire → vérifier note créée avec source_type 'ai_audio_transcript'
6. **Documents** : Upload PDF sur asset via Filament → vérifier sur S3
7. **Rapport tâche** : Compléter une tâche → générer rapport → vérifier PDF/Excel
8. **Rapport session** : Compléter session → générer rapport consolidé → vérifier résumé IA
9. **Quotas** : Dépasser quota storage → vérifier message d'erreur
10. **Download** : Vérifier que le download inclut conditions, notes, media URLs
