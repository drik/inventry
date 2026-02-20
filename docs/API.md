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

## Synchronisation

### `POST /api/tasks/{taskId}/sync`

Envoie les scans offline et reçoit l'état à jour.

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
        "condition_notes": null
      },
      {
        "item_id": null,
        "asset_id": "01HX...",
        "status": "unexpected",
        "scanned_at": "2026-02-20T10:36:00Z",
        "condition_notes": "Hors emplacement"
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
  "items": [ ... ],
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
| 403 | Tâche non assignée à l'utilisateur |
| 404 | Ressource non trouvée |
| 422 | Données invalides ou action impossible |

Toutes les erreurs retournent un JSON avec un champ `message`.

---

## Collection Postman

Importer le fichier `docs/postman_collection.json` dans Postman.

Le login sauvegarde automatiquement le token dans les variables de collection.
