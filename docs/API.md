# Inventry API REST - Documentation

Base URL : `http://localhost:8000/api`

Authentification : **Laravel Sanctum** (Bearer Token)

---

## Authentification

### `POST /api/auth/login`

Obtenir un token d'accès.

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@admin.com","password":"password","device_name":"Mon iPhone"}'
```

**Réponse 200 :**
```json
{
  "token": "1|abc123...",
  "user": {
    "id": "01HX...",
    "name": "Admin",
    "email": "admin@admin.com",
    "role": "super_admin",
    "organization": {
      "id": "01HX...",
      "name": "My Company",
      "slug": "my-company",
      "logo_url": null
    }
  }
}
```

**Erreur 422 :** Identifiants incorrects ou compte désactivé.

### `GET /api/auth/me`

```bash
curl http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### `POST /api/auth/logout`

Révoque le token courant.

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

---

## Dashboard

### `GET /api/dashboard`

Statistiques des tâches de l'utilisateur connecté.

```bash
curl http://localhost:8000/api/dashboard \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse :**
```json
{
  "stats": {
    "pending": 3,
    "in_progress": 1,
    "completed_today": 2,
    "completed_total": 15
  },
  "current_task": {
    "id": "01HX...",
    "session_name": "Inventaire Q1 2026",
    "location_name": "Siège - Lomé",
    "status": "in_progress",
    "progress": {
      "total_expected": 25,
      "total_scanned": 12,
      "total_matched": 10,
      "total_missing": 0,
      "total_unexpected": 2
    }
  }
}
```

`current_task` est `null` si aucune tâche n'est en cours.

---

## Tâches

### `GET /api/tasks`

Liste paginée des tâches assignées à l'utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `status` | string | Filtre : `pending`, `in_progress`, `completed` |
| `page` | int | Page (15 résultats/page) |

```bash
curl "http://localhost:8000/api/tasks?status=pending" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse :**
```json
{
  "data": [
    {
      "id": "01HX...",
      "session": { "id": "...", "name": "Inventaire Q1", "status": "in_progress", "description": "..." },
      "location": { "id": "...", "name": "Siège - Lomé", "city": "Lomé" },
      "status": "pending",
      "started_at": null,
      "completed_at": null,
      "notes": null,
      "items_count": 25,
      "scanned_count": 0,
      "created_at": "2026-02-15T10:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 5 }
}
```

### `GET /api/tasks/{taskId}/download`

Télécharge les données complètes pour le mode hors ligne.

```bash
curl http://localhost:8000/api/tasks/{taskId}/download \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse :**
```json
{
  "task": {
    "id": "01HX...",
    "status": "pending",
    "notes": null,
    "inventory_notes": [
      {
        "id": "01HX...",
        "content": "Zone A terminée sans anomalies",
        "source_type": "text",
        "created_by": "01HX...",
        "creator_name": "Jean Dupont",
        "created_at": "2026-02-20T10:35:00+00:00"
      }
    ]
  },
  "session": { "id": "01HX...", "name": "Inventaire Q1", "status": "in_progress" },
  "location": { "id": "01HX...", "name": "Siège - Lomé", "city": "Lomé" },
  "items": [
    {
      "id": "01HX...",
      "asset_id": "01HX...",
      "status": "pending",
      "scanned_at": null,
      "scanned_by": null,
      "condition_notes": null,
      "condition_id": "01HX...",
      "condition_name": "Bon état",
      "media": [
        {
          "id": "01HX...",
          "collection": "photos",
          "file_name": "photo_001.jpg",
          "mime_type": "image/jpeg",
          "url": "https://s3.../signed-url"
        }
      ],
      "notes": [
        {
          "id": "01HX...",
          "content": "Écran présente une fissure",
          "source_type": "text",
          "source_media_id": null,
          "created_at": "2026-02-20T10:35:00+00:00"
        }
      ]
    }
  ],
  "assets": [
    {
      "id": "01HX...",
      "name": "MacBook Pro 14\"",
      "asset_code": "AST-00001",
      "serial_number": "FVFXJ3K1Q6LR",
      "category_name": "Ordinateurs portables",
      "location_name": "Siège - Lomé",
      "status": "active",
      "model_name": "MacBook Pro 14-inch",
      "model_number": "A2625",
      "supplier_name": "Apple Store Lomé",
      "primary_image_url": "http://localhost:8000/storage/...",
      "tag_values": [
        {
          "id": "01HX...",
          "tag_name": "Serial Number",
          "value": "FVFXJ3K1Q6LR",
          "encoding_mode": "qr_code"
        }
      ]
    }
  ],
  "conditions": [
    { "id": "01HX...", "name": "Bon état", "slug": "good", "color": "#22c55e", "icon": "heroicon-o-check-circle" },
    { "id": "01HX...", "name": "Endommagé", "slug": "damaged", "color": "#ef4444", "icon": "heroicon-o-exclamation-triangle" }
  ],
  "all_asset_barcodes": [
    {
      "asset_id": "01HX...",
      "asset_code": "AST-00001",
      "tag_values": ["FVFXJ3K1Q6LR", "6340971823"]
    }
  ],
  "storage": {
    "used_bytes": 52428800,
    "quota_bytes": 5368709120,
    "percentage": 1,
    "remaining_bytes": 5316280320,
    "overage_bytes": 0
  },
  "downloaded_at": "2026-02-23T10:00:00+00:00"
}
```

`all_asset_barcodes` est un index léger de tous les assets de l'organisation pour la résolution offline (via `asset_code` ou `tag_values`).

### `POST /api/tasks/{taskId}/start`

Démarre une tâche (`pending` → `in_progress`).

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/start \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Erreur 422 :** La tâche n'est pas en statut `pending`.

### `POST /api/tasks/{taskId}/complete`

Termine une tâche. Les items non scannés sont marqués `Missing`. Le créateur de la session est notifié.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/complete \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Erreur 422 :** La tâche n'est pas en statut `in_progress`.

---

## Scan

### `POST /api/tasks/{taskId}/scan`

Scan d'un code en mode connecté.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/scan \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -d '{"barcode":"AST-00001"}'
```

**Résolution** : `barcode` → `asset_code` → `tag values`

**Réponse 200 (trouvé, attendu) :**
```json
{
  "found": true,
  "is_unexpected": false,
  "already_scanned": false,
  "asset": {
    "id": "01HX...",
    "name": "MacBook Pro 14\"",
    "asset_code": "AST-00001",
    "category_name": "Ordinateurs portables",
    "model_name": "MacBook Pro 14-inch",
    "supplier_name": "Apple Store Lomé",
    "primary_image_url": "http://..."
  },
  "item": {
    "id": "01HX...",
    "status": "found",
    "scanned_at": "2026-02-20T10:35:00+00:00"
  }
}
```

**Réponse 200 (inattendu) :** `is_unexpected: true`, `item: null`

**Réponse 404 :** `{ "found": false, "message": "..." }`

### `POST /api/tasks/{taskId}/unexpected`

Ajoute un asset inattendu à la session.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/unexpected \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -d '{"asset_id":"01HX...","condition_notes":"Trouvé salle B"}'
```

**Réponse 201 :** Item créé avec statut `unexpected`.

**Erreur 422 :** Asset déjà dans la session.

---

## Médias (Photos, Audio, Vidéo)

Upload et gestion de fichiers médias sur les items d'inventaire et les tâches. Stockage S3 avec quotas par plan.

### `POST /api/tasks/{taskId}/items/{itemId}/media`

Ajouter un fichier média à un item d'inventaire.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/items/{itemId}/media \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "file=@/path/to/photo.jpg" \
  -F "collection=photos"
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `file` | file | **Requis.** Le fichier à uploader (max 50 Mo) |
| `collection` | string | **Requis.** `photos`, `audio`, ou `video` |

**Types MIME acceptés :**
- `photos` : jpg, jpeg, png, webp
- `audio` : mp3, wav, m4a, ogg, webm
- `video` : mp4, mov, webm

**Réponse 201 :**
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
    "quota_bytes": 5368709120,
    "percentage": 1,
    "remaining_bytes": 5316280320,
    "overage_bytes": 0
  }
}
```

**Erreur 422 :** Quota de stockage dépassé ou type de fichier non autorisé.

### `POST /api/tasks/{taskId}/media`

Ajouter un fichier média à une tâche (même format que ci-dessus).

### `GET /api/media/{mediaId}`

Récupérer les informations et l'URL signée d'un média.

```bash
curl http://localhost:8000/api/media/{mediaId} \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse 200 :**
```json
{
  "media": {
    "id": "01HX...",
    "collection": "photos",
    "file_name": "photo_001.jpg",
    "mime_type": "image/jpeg",
    "size_bytes": 245000,
    "url": "https://s3.../signed-url",
    "metadata": {},
    "created_at": "2026-02-20T10:35:00+00:00"
  }
}
```

### `GET /api/media/{mediaId}/download`

Télécharger un média (redirige vers l'URL signée S3).

### `DELETE /api/media/{mediaId}`

Supprimer un média. Met à jour les quotas de stockage.

---

## Conditions

Gestion des conditions d'assets (personnalisables par organisation). 6 conditions par défaut sont créées à l'inscription.

### `GET /api/conditions`

Liste des conditions de l'organisation.

```bash
curl http://localhost:8000/api/conditions \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse 200 :**
```json
{
  "conditions": [
    { "id": "01HX...", "name": "Neuf", "slug": "new", "color": "#3b82f6", "icon": "heroicon-o-sparkles" },
    { "id": "01HX...", "name": "Bon état", "slug": "good", "color": "#22c55e", "icon": "heroicon-o-check-circle" },
    { "id": "01HX...", "name": "Usé", "slug": "worn", "color": "#f59e0b", "icon": "heroicon-o-minus-circle" },
    { "id": "01HX...", "name": "Endommagé", "slug": "damaged", "color": "#ef4444", "icon": "heroicon-o-exclamation-triangle" },
    { "id": "01HX...", "name": "Non fonctionnel", "slug": "non_functional", "color": "#dc2626", "icon": "heroicon-o-x-circle" },
    { "id": "01HX...", "name": "Hors service", "slug": "out_of_service", "color": "#6b7280", "icon": "heroicon-o-no-symbol" }
  ]
}
```

### `PUT /api/tasks/{taskId}/items/{itemId}/condition`

Changer la condition d'un item d'inventaire.

```bash
curl -X PUT http://localhost:8000/api/tasks/{taskId}/items/{itemId}/condition \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -d '{"condition_id": "01HX..."}'
```

**Réponse 200 :**
```json
{
  "item": {
    "id": "01HX...",
    "condition_id": "01HX...",
    "condition_name": "Endommagé"
  }
}
```

---

## Statut d'item

### `PUT /api/tasks/{taskId}/items/{itemId}/status`

Changer manuellement le statut de scan d'un item (avec audit trail).

```bash
curl -X PUT http://localhost:8000/api/tasks/{taskId}/items/{itemId}/status \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -d '{"status": "found", "reason": "Trouvé dans un autre bureau"}'
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `status` | string | **Requis.** `pending`, `found`, `missing`, `unexpected` |
| `reason` | string | Optionnel. Motif du changement |

**Réponse 200 :**
```json
{
  "item": {
    "id": "01HX...",
    "status": "found",
    "previous_status": "pending"
  },
  "change": {
    "id": "01HX...",
    "from_status": "pending",
    "to_status": "found",
    "reason": "Trouvé dans un autre bureau",
    "created_at": "2026-02-20T10:35:00+00:00"
  }
}
```

**Erreur 422 :** Statut identique ou statut invalide.

---

## Notes

Notes textuelles sur les items d'inventaire et les tâches. Supportent les notes IA (reformulation, description photo, transcription audio, description vidéo).

### `POST /api/tasks/{taskId}/items/{itemId}/notes`

Ajouter une note à un item.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/items/{itemId}/notes \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -d '{
    "content": "L'\''écran présente une fissure en bas à gauche",
    "source_type": "text"
  }'
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `content` | string | **Requis.** Contenu de la note |
| `source_type` | string | Optionnel. `text` (défaut), `ai_rephrase`, `ai_photo_desc`, `ai_audio_transcript`, `ai_video_desc` |
| `source_media_id` | string | Optionnel. ID du média source (photo/audio/vidéo) |
| `original_content` | string | Optionnel. Texte original avant reformulation IA |

**Réponse 201 :**
```json
{
  "note": {
    "id": "01HX...",
    "content": "L'écran présente une fissure en bas à gauche",
    "source_type": "text",
    "source_media_id": null,
    "original_content": null,
    "created_by": "01HX...",
    "created_at": "2026-02-20T10:35:00+00:00"
  }
}
```

### `POST /api/tasks/{taskId}/notes`

Ajouter une note à la tâche (même format).

### `GET /api/tasks/{taskId}/items/{itemId}/notes`

Lister les notes d'un item.

### `GET /api/tasks/{taskId}/notes`

Lister les notes d'une tâche.

### `DELETE /api/notes/{noteId}`

Supprimer une note.

---

## Assets

CRUD complet sur les assets + création assistée par IA (multi-image).

### `GET /api/assets`

Liste paginée des assets de l'organisation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `search` | string | Recherche sur nom, asset_code, tag values |
| `category_id` | string | Filtre par catégorie |
| `location_id` | string | Filtre par emplacement |
| `status` | string | Filtre par statut |
| `page` | int | Page (20 résultats/page) |

```bash
curl "http://localhost:8000/api/assets?search=MacBook&category_id=01HX..." \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse 200 :**
```json
{
  "data": [
    {
      "id": "01HX...",
      "asset_code": "AST-00001",
      "name": "MacBook Pro 14\"",
      "category_name": "Ordinateurs portables",
      "location_name": "Siège - Lomé",
      "manufacturer_name": "Apple",
      "model_name": "MacBook Pro 14-inch",
      "status": "available",
      "primary_image_url": "http://localhost:8000/storage/...",
      "tag_values": [
        { "tag_name": "Serial Number", "value": "FVFXJ3K1Q6LR" }
      ],
      "created_at": "2026-02-20T10:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 5 }
}
```

### `GET /api/assets/{id}`

Détail complet d'un asset avec toutes ses relations.

```bash
curl http://localhost:8000/api/assets/{id} \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### `POST /api/assets`

Création manuelle d'un asset. **Middleware** : `plan.limit:max_assets`

```bash
curl -X POST http://localhost:8000/api/assets \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -d '{
    "name": "MacBook Pro 14\"",
    "category_id": "01HX...",
    "location_id": "01HX...",
    "manufacturer_id": "01HX...",
    "model_id": "01HX...",
    "status": "available",
    "notes": "Neuf, sous garantie",
    "tag_values": [
      { "asset_tag_id": "01HX...", "value": "FVFXJ3K1Q6LR" }
    ]
  }'
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `name` | string | **Requis.** Nom de l'asset |
| `category_id` | string | **Requis.** ID catégorie |
| `location_id` | string | **Requis.** ID emplacement |
| `manufacturer_id` | string | Optionnel. ID fabricant |
| `model_id` | string | Optionnel. ID modèle |
| `status` | string | Optionnel. Défaut : `available` |
| `purchase_date` | date | Optionnel. Date d'achat |
| `purchase_cost` | numeric | Optionnel. Coût d'achat |
| `notes` | string | Optionnel. Notes |
| `tag_values` | array | Optionnel. Valeurs de tags |
| `image` | file | Optionnel. Image principale |

**Réponse 201 :** Asset créé avec ses relations.

### `PUT /api/assets/{id}`

Mise à jour d'un asset. Mêmes champs que la création mais tous optionnels.

```bash
curl -X PUT http://localhost:8000/api/assets/{id} \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -d '{"name": "MacBook Pro 14\" - Marie", "status": "in_use"}'
```

### `DELETE /api/assets/{id}`

Suppression douce d'un asset.

```bash
curl -X DELETE http://localhost:8000/api/assets/{id} \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### `POST /api/assets/ai-extract`

Extraction d'informations produit par IA à partir d'une ou plusieurs photos (sans créer l'asset).

**Middlewares** : `throttle:ai-vision`, `plan.limit:max_ai_requests_daily`

Supporte `photos` (array, multi-image) et `photo` (singulier, rétrocompatibilité).

```bash
# Multi-image (recommandé)
curl -X POST http://localhost:8000/api/assets/ai-extract \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "photos[]=@/path/to/front.jpg" \
  -F "photos[]=@/path/to/label.jpg" \
  -F "photos[]=@/path/to/serial.jpg"

# Single image (rétrocompat)
curl -X POST http://localhost:8000/api/assets/ai-extract \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "photo=@/path/to/photo.jpg"
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `photos` | file[] | **Requis** (sauf si `photo`). 1 à 5 images JPEG/PNG, max 2048 Ko chacune |
| `photo` | file | **Requis** (sauf si `photos`). Image unique (rétrocompatibilité) |

**Réponse 200 :**
```json
{
  "recognition_log_id": "01HX...",
  "extraction": {
    "suggested_name": "iPhone 15 Pro",
    "suggested_category": "Téléphones",
    "suggested_brand": "Apple",
    "suggested_model": "iPhone 15 Pro",
    "description": "Smartphone Apple dans sa boîte d'origine",
    "serial_number": "F4GH7K9L2M",
    "sku": null,
    "detected_text": ["iPhone 15 Pro", "F4GH7K9L2M", "Apple"],
    "confidence": 0.95
  },
  "resolved_ids": {
    "category_id": "01HX...",
    "manufacturer_id": "01HX...",
    "model_id": "01HX...",
    "unmatched_suggestions": {}
  },
  "image_paths": [
    "ai-captures/01HX.../2026-02-25/abc123.jpg",
    "ai-captures/01HX.../2026-02-25/def456.jpg",
    "ai-captures/01HX.../2026-02-25/ghi789.jpg"
  ],
  "usage": {
    "daily_used": 5,
    "daily_limit": 30,
    "monthly_used": 42,
    "monthly_limit": 500
  }
}
```

**Erreur 403 :** Quota mensuel atteint.

**Erreur 422 :** Plus de 5 photos ou format invalide.

**Erreur 503 :** AI Vision désactivée.

### `POST /api/assets/ai-create`

Extraction IA + création automatique de l'asset. Combine les infos de toutes les photos.

**Middlewares** : `throttle:ai-vision`, `plan.limit:max_ai_requests_daily`, `plan.limit:max_assets`

```bash
curl -X POST http://localhost:8000/api/assets/ai-create \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "photos[]=@/path/to/front.jpg" \
  -F "photos[]=@/path/to/label.jpg" \
  -F "location_id=01HX..."
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `photos` | file[] | **Requis** (sauf si `photo`). 1 à 5 images JPEG/PNG |
| `photo` | file | **Requis** (sauf si `photos`). Image unique (rétrocompat) |
| `location_id` | string | **Requis.** Emplacement (l'IA ne peut pas le deviner) |
| `name` | string | Optionnel. Override du nom suggéré par l'IA |
| `category_id` | string | Optionnel. Override de la catégorie |
| `manufacturer_id` | string | Optionnel. Override du fabricant |
| `model_id` | string | Optionnel. Override du modèle |
| `notes` | string | Optionnel. Override des notes |

**Réponse 201 :** Asset créé + données d'extraction + images associées (première = primary).

**Erreurs :** Mêmes que `ai-extract` (403, 422, 503).

---

## Documents (sur Assets)

Gestion de documents (PDF, Excel, images) attachés aux assets.

### `POST /api/assets/{assetId}/documents`

Uploader un document sur un asset.

```bash
curl -X POST http://localhost:8000/api/assets/{assetId}/documents \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "file=@/path/to/facture.pdf"
```

**Types MIME acceptés :** pdf, doc, docx, xls, xlsx, jpg, jpeg, png

**Réponse 201 :** Même format que l'upload de média.

### `GET /api/assets/{assetId}/documents`

Lister les documents d'un asset.

**Réponse 200 :**
```json
{
  "documents": [
    {
      "id": "01HX...",
      "file_name": "facture_2026.pdf",
      "mime_type": "application/pdf",
      "size_bytes": 125000,
      "url": "https://s3.../signed-url",
      "uploaded_by": "01HX...",
      "created_at": "2026-02-20T10:35:00+00:00"
    }
  ]
}
```

---

## Rapports d'inventaire

Génération de rapports de tâche et de session (PDF, Excel, résumé IA).

### `POST /api/tasks/{taskId}/report`

Générer un rapport pour une tâche.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/report \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse 201 :**
```json
{
  "report": {
    "id": "01HX...",
    "type": "task_report",
    "title": "Rapport - Tâche Siège Lomé",
    "summary": null,
    "data": {
      "total_expected": 25,
      "total_found": 22,
      "total_missing": 2,
      "total_unexpected": 1,
      "completion_rate": 88,
      "conditions": { "good": 18, "damaged": 3, "worn": 1 }
    },
    "created_at": "2026-02-20T10:35:00+00:00"
  }
}
```

### `GET /api/tasks/{taskId}/report`

Voir le rapport d'une tâche.

### `GET /api/sessions/{sessionId}/report`

Voir le rapport consolidé d'une session.

### `GET /api/reports/{reportId}/pdf`

Télécharger le rapport en PDF (URL signée S3).

### `GET /api/reports/{reportId}/excel`

Télécharger le rapport en Excel (URL signée S3).

---

## Abonnement

### `GET /api/subscription/current`

Informations sur l'abonnement de l'organisation.

```bash
curl http://localhost:8000/api/subscription/current \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse 200 :**
```json
{
  "plan": {
    "id": "01HX...",
    "name": "Pro",
    "slug": "pro",
    "price_monthly": "35.00",
    "price_yearly": "350.00"
  },
  "subscription": {
    "is_subscribed": true,
    "on_trial": false,
    "trial_ends_at": null,
    "status": "active"
  }
}
```

### `GET /api/subscription/plans`

Liste de tous les plans disponibles.

```bash
curl http://localhost:8000/api/subscription/plans \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse 200 :**
```json
{
  "plans": [
    {
      "id": "01HX...",
      "name": "Freemium",
      "slug": "freemium",
      "description": "...",
      "price_monthly": "0.00",
      "price_yearly": "0.00",
      "formatted_monthly_price": "0 €",
      "formatted_yearly_price": "0 €",
      "limits": {
        "max_users": 2,
        "max_assets": 50,
        "max_ai_requests_daily": 3,
        "max_ai_requests_monthly": 30
      }
    }
  ]
}
```

### `GET /api/subscription/usage`

Utilisation des quotas et fonctionnalités de l'organisation.

```bash
curl http://localhost:8000/api/subscription/usage \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse 200 :**
```json
{
  "usage": {
    "max_users": { "label": "Utilisateurs", "current": 3, "limit": 10, "percentage": 30, "is_unlimited": false, "is_disabled": false },
    "max_assets": { "label": "Assets", "current": 45, "limit": 500, "percentage": 9, "is_unlimited": false, "is_disabled": false },
    "max_locations": { "label": "Emplacements", "current": 2, "limit": 20, "percentage": 10, "is_unlimited": false, "is_disabled": false },
    "max_active_inventory_sessions": { "label": "Sessions actives", "current": 1, "limit": 5, "percentage": 20, "is_unlimited": false, "is_disabled": false },
    "max_ai_requests_daily": { "label": "Requêtes IA (jour)", "current": 5, "limit": 30, "percentage": 16, "is_unlimited": false, "is_disabled": false },
    "max_ai_requests_monthly": { "label": "Requêtes IA (mois)", "current": 42, "limit": 500, "percentage": 8, "is_unlimited": false, "is_disabled": false }
  },
  "features": {
    "has_api_access": { "label": "Accès API", "enabled": true },
    "has_export": { "label": "Export", "enabled": true },
    "has_advanced_analytics": { "label": "Analytiques avancées", "enabled": false },
    "has_custom_integrations": { "label": "Intégrations personnalisées", "enabled": false },
    "has_priority_support": { "label": "Support prioritaire", "enabled": false }
  }
}
```

---

## AI Vision

Reconnaissance d'assets par intelligence artificielle. Utilise Gemini Flash (Freemium/Basic/Pro) ou GPT-4o (Premium) avec fallback automatique.

**Middlewares :**
- `throttle:ai-vision` — 10 requêtes/minute par organisation (anti-abus)
- `plan.limit:max_ai_requests_daily` — quota journalier selon le plan
- Vérification quota mensuel dans le contrôleur

### `POST /api/tasks/{taskId}/ai-identify`

Identifie un asset à partir d'une photo et cherche des correspondances parmi les assets de l'organisation.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/ai-identify \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "photo=@/path/to/photo.jpg" \
  -F "bbox_x=0.1" \
  -F "bbox_y=0.2" \
  -F "bbox_width=0.5" \
  -F "bbox_height=0.6"
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `photo` | file | **Requis.** Image JPEG/PNG, max 2048 Ko |
| `bbox_x` | float | Optionnel. Coordonnée X du coin supérieur gauche (0-1) |
| `bbox_y` | float | Optionnel. Coordonnée Y du coin supérieur gauche (0-1) |
| `bbox_width` | float | Optionnel. Largeur de la zone (0.01-1) |
| `bbox_height` | float | Optionnel. Hauteur de la zone (0.01-1) |

Les 4 paramètres `bbox_*` doivent être fournis ensemble ou pas du tout. Ils permettent de cadrer une zone d'intérêt dans la photo.

**Réponse 200 :**
```json
{
  "recognition_log_id": "01HX...",
  "identification": {
    "suggested_category": "Ordinateurs portables",
    "suggested_brand": "Apple",
    "suggested_model": "MacBook Pro 14\"",
    "detected_text": ["FVFXJ3K1Q6LR", "MacBook Pro"],
    "confidence": 0.92,
    "description": "Ordinateur portable argenté avec logo Apple"
  },
  "matches": [
    {
      "asset_id": "01HX...",
      "asset_name": "MacBook Pro 14\" - Marie",
      "asset_code": "AST-00042",
      "category_name": "Ordinateurs portables",
      "location_name": "Siège - Lomé",
      "model_name": "MacBook Pro 14-inch",
      "primary_image_url": "http://localhost:8000/storage/...",
      "confidence": 0.88,
      "reasoning": "Même modèle, numéro de série visible correspond",
      "inventory_status": "pending"
    }
  ],
  "has_strong_match": true,
  "usage": {
    "daily_used": 5,
    "daily_limit": 30,
    "monthly_used": 42,
    "monthly_limit": 500
  }
}
```

`inventory_status` peut être : `pending`, `found`, `missing`, `unexpected`, ou `not_in_session`.

**Erreur 403 :** Quota mensuel atteint.

**Erreur 429 :** Rate limit (trop de requêtes par minute).

**Erreur 503 :** AI Vision désactivée (`AI_VISION_ENABLED=false`).

### `POST /api/tasks/{taskId}/ai-verify`

Vérifie qu'une photo correspond bien à un asset spécifique.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/ai-verify \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "photo=@/path/to/photo.jpg" \
  -F "asset_id=01HX..." \
  -F "bbox_x=0.1" \
  -F "bbox_y=0.2" \
  -F "bbox_width=0.5" \
  -F "bbox_height=0.6"
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `photo` | file | **Requis.** Image JPEG/PNG, max 2048 Ko |
| `asset_id` | string | **Requis.** ID de l'asset à vérifier |
| `bbox_x` | float | Optionnel. Coordonnée X du coin supérieur gauche (0-1) |
| `bbox_y` | float | Optionnel. Coordonnée Y du coin supérieur gauche (0-1) |
| `bbox_width` | float | Optionnel. Largeur de la zone (0.01-1) |
| `bbox_height` | float | Optionnel. Hauteur de la zone (0.01-1) |

**Réponse 200 :**
```json
{
  "recognition_log_id": "01HX...",
  "is_match": true,
  "confidence": 0.95,
  "reasoning": "Le modèle, la couleur et le numéro de série correspondent à l'asset de référence.",
  "discrepancies": [],
  "usage": {
    "daily_used": 6,
    "daily_limit": 30,
    "monthly_used": 43,
    "monthly_limit": 500
  }
}
```

**Réponse 200 (non-correspondance) :**
```json
{
  "recognition_log_id": "01HX...",
  "is_match": false,
  "confidence": 0.30,
  "reasoning": "La couleur et le modèle ne correspondent pas.",
  "discrepancies": ["Couleur différente (noir vs argenté)", "Modèle différent"],
  "usage": { "..." }
}
```

**Erreurs :** Mêmes que `ai-identify` (403, 429, 503).

### `POST /api/tasks/{taskId}/ai-confirm`

Confirme ou rejette une suggestion IA. Pas de middleware de quota (ne consomme pas de requête IA).

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/ai-confirm \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -d '{
    "recognition_log_id": "01HX...",
    "asset_id": "01HX...",
    "action": "matched"
  }'
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `recognition_log_id` | string | ID du log de reconnaissance |
| `asset_id` | string | ID de l'asset (requis sauf si `action=dismissed`) |
| `action` | string | `matched`, `unexpected`, ou `dismissed` |

**Réponse 200 (`matched`) :**
```json
{
  "action": "matched",
  "item": {
    "id": "01HX...",
    "asset_id": "01HX...",
    "status": "found",
    "scanned_at": "2026-02-21T10:35:00+00:00",
    "identification_method": "ai_vision"
  }
}
```

**Réponse 201 (`unexpected`) :**
```json
{
  "action": "unexpected",
  "item": {
    "id": "01HX...",
    "asset_id": "01HX...",
    "status": "unexpected",
    "scanned_at": "2026-02-21T10:35:00+00:00",
    "identification_method": "ai_vision"
  }
}
```

**Réponse 200 (`dismissed`) :**
```json
{
  "action": "dismissed",
  "message": "Suggestion IA annulée."
}
```

**Erreur 422 :** Asset déjà dans la session (pour `unexpected`).

---

## AI Assistant (Notes IA)

Assistance IA pour les notes d'inventaire : reformulation, description de photo, transcription audio, description vidéo. Utilise Gemini 2.5 Flash (Freemium/Basic/Pro) ou GPT-4o/Whisper (Premium) avec fallback automatique.

**Middlewares :** `throttle:ai-vision`, `plan.limit:max_ai_requests_daily`

### `POST /api/tasks/{taskId}/ai-rephrase`

Reformule un texte de manière professionnelle.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/ai-rephrase \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -d '{"text": "le pc il est cassé l'\''écran"}'
```

**Réponse 200 :**
```json
{
  "text": "L'écran de l'ordinateur portable est endommagé.",
  "usage": {
    "provider": "gemini",
    "prompt_tokens": 120,
    "completion_tokens": 25,
    "estimated_cost_usd": 0.001
  }
}
```

### `POST /api/tasks/{taskId}/ai-describe-photo`

Décrit une photo prise lors de l'inventaire.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/ai-describe-photo \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "photo=@/path/to/photo.jpg"
```

**Réponse 200 :**
```json
{
  "description": "Ordinateur portable Dell Latitude avec un écran fissuré en bas à gauche. L'appareil présente des traces d'usure sur le clavier. Numéro de série visible : ABC123.",
  "media_id": "01HX...",
  "usage": { "..." }
}
```

### `POST /api/tasks/{taskId}/ai-transcribe`

Transcrit un enregistrement audio.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/ai-transcribe \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "audio=@/path/to/recording.m4a"
```

**Réponse 200 :**
```json
{
  "transcription": "L'imprimante ne s'allume plus depuis ce matin. Le câble d'alimentation semble en bon état.",
  "media_id": "01HX...",
  "usage": { "..." }
}
```

### `POST /api/tasks/{taskId}/ai-describe-video`

Décrit une courte vidéo.

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/ai-describe-video \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "video=@/path/to/video.mp4"
```

**Réponse 200 :**
```json
{
  "description": "La vidéo montre un agent manipulant un projecteur Epson. L'agent démontre que la lampe ne s'allume pas malgré plusieurs tentatives.",
  "media_id": "01HX...",
  "usage": { "..." }
}
```

**Erreurs communes IA :** 403 (quota atteint), 429 (rate limit), 422 (fichier invalide).

### Workflow mobile pour notes IA

1. L'agent choisit le mode (texte, photo, audio, vidéo)
2. Capture/enregistre le contenu
3. Upload le fichier : `POST /api/tasks/{taskId}/items/{itemId}/media`
4. Appelle l'endpoint IA : `POST /api/tasks/{taskId}/ai-describe-photo`
5. L'IA retourne le texte généré
6. L'agent peut éditer le texte
7. Crée la note : `POST /api/tasks/{taskId}/items/{itemId}/notes` avec `source_type: "ai_photo_desc"` et `source_media_id`

---

## Synchronisation

### `POST /api/tasks/{taskId}/sync`

Envoie les scans offline et reçoit l'état à jour.

| Champ scan | Type | Description |
|------------|------|-------------|
| `item_id` | string\|null | ID de l'item existant (null pour unexpected) |
| `asset_id` | string\|null | ID de l'asset (requis si item_id est null) |
| `status` | string | `found` ou `unexpected` |
| `scanned_at` | datetime | Date/heure du scan (ISO 8601) |
| `condition_notes` | string\|null | Notes sur l'état |
| `identification_method` | string\|null | `barcode`, `nfc`, `ai_vision`, ou `manual` (défaut: `barcode`) |
| `ai_recognition_log_id` | string\|null | ID du log AI (si identifié par IA) |
| `ai_confidence` | float\|null | Score de confiance IA entre 0 et 1 |

```bash
curl -X POST http://localhost:8000/api/tasks/{taskId}/sync \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -d '{
    "scans": [
      {
        "item_id": "01HX...",
        "status": "found",
        "scanned_at": "2026-02-20T10:35:00Z",
        "condition_notes": null,
        "identification_method": "barcode"
      },
      {
        "item_id": null,
        "asset_id": "01HX...",
        "status": "unexpected",
        "scanned_at": "2026-02-20T10:36:00Z",
        "condition_notes": "Identifié par IA",
        "identification_method": "ai_vision",
        "ai_recognition_log_id": "01HX...",
        "ai_confidence": 0.88
      }
    ],
    "task_status": "in_progress",
    "task_notes": "Zone A terminée",
    "last_synced_at": "2026-02-20T10:00:00Z"
  }'
```

**Réponse :**
```json
{
  "synced_count": 2,
  "conflicts": [],
  "task": { "id": "01HX...", "status": "in_progress" },
  "items": [
    {
      "id": "01HX...",
      "asset_id": "01HX...",
      "status": "found",
      "scanned_at": "2026-02-20T10:35:00+00:00",
      "scanned_by": "01HX...",
      "condition_notes": null,
      "identification_method": "barcode",
      "ai_recognition_log_id": null,
      "ai_confidence": null
    }
  ],
  "synced_at": "2026-02-20T10:40:00+00:00"
}
```

**Conflits** : Si un item a été scanné par un autre utilisateur avec un `scanned_at` plus récent :
```json
{
  "conflicts": [
    {
      "item_id": "01HX...",
      "reason": "already_scanned_by_another_user",
      "server_scanned_at": "2026-02-20T10:34:00+00:00",
      "server_scanned_by": "Marie Dupont"
    }
  ]
}
```

### `GET /api/tasks/{taskId}/sync-status?since=2026-02-20T10:00:00Z`

Vérifie s'il y a des changements serveur depuis le dernier sync.

```bash
curl "http://localhost:8000/api/tasks/{taskId}/sync-status?since=2026-02-20T10:00:00Z" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Réponse :**
```json
{
  "has_changes": true,
  "server_updated_at": "2026-02-20T10:38:00",
  "items_changed": 3
}
```

---

## Erreurs communes

| Code | Description |
|------|-------------|
| 401 | Token invalide ou expiré |
| 403 | Tâche non assignée à l'utilisateur ou quota de plan atteint |
| 404 | Ressource non trouvée |
| 422 | Données invalides ou action impossible |
| 429 | Rate limit atteint (trop de requêtes par minute) |
| 503 | Fonctionnalité désactivée (ex: AI Vision) |

Toutes les erreurs retournent un JSON avec un champ `message`.

---

## Collection Postman

Importer le fichier `docs/postman_collection.json` dans Postman.

Le login sauvegarde automatiquement le token dans les variables de collection.
