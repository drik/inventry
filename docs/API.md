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

**Réponse :** Contient `task`, `session`, `location`, `items`, `assets` (avec images et tags), et `all_asset_barcodes` (index léger de tous les barcodes de l'organisation pour résolution offline).

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

Scan d'un barcode en mode connecté.

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
  -F "photo=@/path/to/photo.jpg"
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `photo` | file | Image JPEG/PNG, max 2048 Ko |

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
  -F "asset_id=01HX..."
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `photo` | file | Image JPEG/PNG, max 2048 Ko |
| `asset_id` | string | ID de l'asset à vérifier |

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
