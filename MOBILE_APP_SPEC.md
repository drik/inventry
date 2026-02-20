# Spécification : Application Mobile Inventry Scanner + API REST

## Contexte

La plateforme **Inventry** (Laravel/Filament) gère l'inventaire physique d'une organisation. Les sessions d'inventaire sont planifiées via le web, avec des tâches assignées à des utilisateurs pour scanner les assets dans des emplacements spécifiques. Actuellement, le scan se fait via une page web mobile (`execute-inventory-task-mobile.blade.php`) avec caméra et NFC.

**Objectif** : Concevoir une application mobile native (React Native / Expo) dédiée au scan barcode et NFC, fonctionnant **en mode hors ligne**. L'application doit permettre aux agents de terrain de :
- Se connecter et voir un tableau de bord de leurs tâches
- Télécharger les données d'une tâche en local pour travailler sans réseau
- Scanner les assets (caméra + NFC) et enregistrer la progression localement
- Synchroniser automatiquement (si connecté) ou manuellement avec le serveur

Ce document est une **spécification destinée à un développeur externe** pour réaliser l'application mobile et les endpoints API côté backend.

---

## PARTIE 1 : API REST (Backend Laravel)

### 1.1 Installation et configuration

**Package à installer** : `laravel/sanctum` (authentification par token API)

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

**Modifications requises** :
- `app/Models/User.php` : ajouter le trait `HasApiTokens`
- `config/auth.php` : ajouter le guard `sanctum`
- Créer `routes/api.php` avec les routes API

**Fichiers à créer** :
- `routes/api.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Api/DashboardController.php`
- `app/Http/Controllers/Api/TaskController.php`
- `app/Http/Controllers/Api/SyncController.php`
- `app/Http/Middleware/EnsureOrganizationAccess.php`

### 1.2 Authentification

#### `POST /api/auth/login`

Login et obtention d'un token Sanctum.

**Request** :
```json
{
  "email": "user@example.com",
  "password": "password",
  "device_name": "iPhone 15 de Kodjo"
}
```

**Response 200** :
```json
{
  "token": "1|abc123...",
  "user": {
    "id": "01HX...",
    "name": "Kodjo Assou",
    "email": "user@example.com",
    "role": "technician",
    "organization": {
      "id": "01HX...",
      "name": "My Company",
      "slug": "my-company",
      "logo_url": "https://..."
    }
  }
}
```

**Response 401** :
```json
{ "message": "Identifiants incorrects." }
```

#### `POST /api/auth/logout`

Révoque le token courant.

**Headers** : `Authorization: Bearer {token}`

**Response 200** :
```json
{ "message": "Déconnexion réussie." }
```

#### `GET /api/auth/me`

Retourne les informations de l'utilisateur connecté.

**Headers** : `Authorization: Bearer {token}`

**Response 200** : Même format que le champ `user` du login.

### 1.3 Dashboard / Statistiques

#### `GET /api/dashboard`

Statistiques des tâches de l'utilisateur connecté.

**Headers** : `Authorization: Bearer {token}`

**Response 200** :
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

**Logique backend** :
- Filtrer `InventoryTask` par `assigned_to = Auth::id()` et `organization_id` de l'utilisateur
- `pending` : tâches avec `status = "pending"` dont la session est `InProgress`
- `in_progress` : tâches avec `status = "in_progress"`
- `completed_today` : tâches avec `status = "completed"` et `completed_at` aujourd'hui
- `completed_total` : total des tâches complétées
- `current_task` : la tâche `in_progress` la plus récente (ou null)

### 1.4 Liste des tâches

#### `GET /api/tasks`

Liste des tâches de l'utilisateur connecté.

**Query params** :
- `status` (optionnel) : `pending`, `in_progress`, `completed` — filtre par statut
- `page` (optionnel) : pagination (15 par page)

**Headers** : `Authorization: Bearer {token}`

**Response 200** :
```json
{
  "data": [
    {
      "id": "01HX...",
      "session": {
        "id": "01HX...",
        "name": "Inventaire Q1 2026",
        "status": "in_progress",
        "description": "Inventaire trimestriel..."
      },
      "location": {
        "id": "01HX...",
        "name": "Siège - Lomé",
        "city": "Lomé"
      },
      "status": "pending",
      "started_at": null,
      "completed_at": null,
      "notes": null,
      "items_count": 25,
      "scanned_count": 0,
      "created_at": "2026-02-15T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "total": 20
  }
}
```

### 1.5 Téléchargement d'une tâche (pour mode hors ligne)

#### `GET /api/tasks/{taskId}/download`

Télécharge toutes les données nécessaires pour travailler hors ligne sur une tâche.

**Headers** : `Authorization: Bearer {token}`

**Response 200** :
```json
{
  "task": {
    "id": "01HX...",
    "status": "pending",
    "notes": null
  },
  "session": {
    "id": "01HX...",
    "name": "Inventaire Q1 2026",
    "status": "in_progress"
  },
  "location": {
    "id": "01HX...",
    "name": "Siège - Lomé",
    "city": "Lomé"
  },
  "items": [
    {
      "id": "01HX...",
      "asset_id": "01HX...",
      "status": "expected",
      "scanned_at": null,
      "scanned_by": null,
      "condition_notes": null
    }
  ],
  "assets": [
    {
      "id": "01HX...",
      "name": "MacBook Pro 14\" M3",
      "asset_code": "AST-00001",
      "barcode": "ABC123XYZ789",
      "serial_number": "C02XX1234567",
      "category_name": "Ordinateurs portables",
      "location_name": "Siège - Lomé",
      "status": "available",
      "primary_image_url": "https://.../storage/assets/01HX.jpg",
      "tag_values": [
        {
          "id": "01HX...",
          "tag_name": "Numéro de série",
          "value": "C02XX1234567",
          "encoding_mode": "qr_code"
        }
      ]
    }
  ],
  "all_asset_barcodes": [
    {
      "asset_id": "01HX...",
      "barcode": "ABC123XYZ789",
      "asset_code": "AST-00001",
      "tag_values": ["C02XX1234567", "RFID-001"]
    }
  ],
  "downloaded_at": "2026-02-19T10:30:00Z"
}
```

**Note** : `all_asset_barcodes` contient tous les assets de l'organisation pour la résolution hors ligne des assets inattendus.

### 1.6 Synchronisation

#### `POST /api/tasks/{taskId}/sync`

Envoi des scans effectués hors ligne et réception de l'état à jour.

**Request** :
```json
{
  "scans": [
    {
      "item_id": "01HX...",
      "status": "found",
      "scanned_at": "2026-02-19T10:35:00Z",
      "condition_notes": "Écran rayé"
    },
    {
      "item_id": null,
      "asset_id": "01HX...",
      "status": "unexpected",
      "scanned_at": "2026-02-19T10:36:00Z",
      "condition_notes": null
    }
  ],
  "task_status": "in_progress",
  "task_notes": "Zone A terminée, zone B en cours",
  "last_synced_at": "2026-02-19T10:00:00Z"
}
```

**Règle de conflit** : si un item a déjà été scanné par un autre utilisateur avec un `scanned_at` plus récent, le scan mobile est ignoré.

**Response 200** :
```json
{
  "synced_count": 5,
  "conflicts": [
    {
      "item_id": "01HX...",
      "reason": "already_scanned_by_another_user",
      "server_scanned_at": "2026-02-19T10:34:00Z",
      "server_scanned_by": "Marie Dupont"
    }
  ],
  "task": { "id": "01HX...", "status": "in_progress" },
  "items": [ ... ],
  "synced_at": "2026-02-19T10:40:00Z"
}
```

#### `GET /api/tasks/{taskId}/sync-status`

**Query params** : `since` (datetime ISO 8601)

**Response 200** :
```json
{
  "has_changes": true,
  "server_updated_at": "2026-02-19T10:38:00Z",
  "items_changed": 3
}
```

### 1.7 Actions sur une tâche

#### `POST /api/tasks/{taskId}/start`
Démarre une tâche (`pending` → `in_progress`).

#### `POST /api/tasks/{taskId}/complete`
Termine une tâche. Marque les items non scannés comme `Missing`, envoie la notification, met à jour les compteurs.

### 1.8 Résolution de barcode (mode en ligne)

#### `POST /api/tasks/{taskId}/scan`

**Request** : `{ "barcode": "ABC123XYZ789" }`

**Logique de résolution** :
1. Chercher par `Asset.barcode` (exact)
2. Sinon, chercher par `Asset.asset_code` (exact)
3. Sinon, chercher par `AssetTagValue.value` (exact)
4. Si trouvé → vérifier si l'asset est dans les items de la session → `is_unexpected` true/false

#### `POST /api/tasks/{taskId}/unexpected`
Ajoute un asset inattendu à la session.

### 1.9 Résumé des routes

```
POST   /api/auth/login                    → AuthController@login
POST   /api/auth/logout                   → AuthController@logout       [auth:sanctum]
GET    /api/auth/me                       → AuthController@me           [auth:sanctum]

GET    /api/dashboard                     → DashboardController@index   [auth:sanctum]

GET    /api/tasks                         → TaskController@index        [auth:sanctum]
GET    /api/tasks/{taskId}/download       → TaskController@download     [auth:sanctum]
POST   /api/tasks/{taskId}/start          → TaskController@start        [auth:sanctum]
POST   /api/tasks/{taskId}/complete       → TaskController@complete     [auth:sanctum]
POST   /api/tasks/{taskId}/scan           → TaskController@scan         [auth:sanctum]
POST   /api/tasks/{taskId}/unexpected     → TaskController@unexpected   [auth:sanctum]

POST   /api/tasks/{taskId}/sync           → SyncController@sync         [auth:sanctum]
GET    /api/tasks/{taskId}/sync-status    → SyncController@status       [auth:sanctum]
```

---

## PARTIE 2 : Application Mobile (React Native / Expo)

### 2.1 Stack technique

| Composant | Technologie |
|-----------|------------|
| Framework | React Native avec Expo (SDK 52+) |
| Navigation | Expo Router (file-based routing) |
| State management | Zustand |
| Data fetching | @tanstack/react-query v5 |
| Base locale | expo-sqlite |
| Scanner caméra | expo-camera (barcode scanning) |
| NFC | react-native-nfc-manager |
| HTTP client | axios |
| UI | React Native Paper ou NativeWind (Tailwind) |
| Icônes | @expo/vector-icons |
| Stockage sécurisé | expo-secure-store (pour le token) |
| Connectivité | @react-native-community/netinfo |

### 2.2 Architecture

```
app/
├── (auth)/login.tsx
├── (tabs)/
│   ├── _layout.tsx
│   ├── index.tsx          # Dashboard
│   └── tasks.tsx          # Liste des tâches
├── task/
│   ├── [id].tsx           # Détail tâche
│   └── [id]/scan.tsx      # Écran de scan
├── _layout.tsx

src/
├── api/                   # Endpoints API (axios)
├── db/                    # SQLite schema + repository
├── stores/                # Zustand (auth, sync)
├── hooks/                 # useBarcodeScan, useNfcScan, useSync...
├── services/              # syncService, barcodeResolver
├── components/            # TaskCard, SyncIndicator, OfflineBadge...
└── types/                 # TypeScript types
```

### 2.3 Schéma SQLite local

```sql
CREATE TABLE tasks (
  id TEXT PRIMARY KEY,
  session_id TEXT NOT NULL, session_name TEXT, session_status TEXT,
  location_id TEXT, location_name TEXT,
  status TEXT NOT NULL,
  notes TEXT, started_at TEXT, completed_at TEXT,
  downloaded_at TEXT NOT NULL, last_synced_at TEXT,
  is_dirty INTEGER DEFAULT 0
);

CREATE TABLE items (
  id TEXT PRIMARY KEY,
  task_id TEXT NOT NULL, asset_id TEXT,
  status TEXT NOT NULL,
  scanned_at TEXT, condition_notes TEXT,
  is_synced INTEGER DEFAULT 1,
  is_local_only INTEGER DEFAULT 0,
  FOREIGN KEY (task_id) REFERENCES tasks(id)
);

CREATE TABLE assets (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL, asset_code TEXT, barcode TEXT,
  serial_number TEXT, category_name TEXT,
  location_name TEXT, status TEXT, primary_image_url TEXT
);

CREATE TABLE barcode_index (
  asset_id TEXT NOT NULL,
  code_type TEXT NOT NULL,    -- barcode | asset_code | tag_value
  code_value TEXT NOT NULL,
  UNIQUE(code_type, code_value)
);

CREATE TABLE pending_scans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id TEXT NOT NULL,
  item_id TEXT, asset_id TEXT,
  status TEXT NOT NULL,
  scanned_at TEXT NOT NULL,
  condition_notes TEXT,
  synced INTEGER DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_barcode_index_value ON barcode_index(code_value);
CREATE INDEX idx_items_task ON items(task_id);
CREATE INDEX idx_pending_task ON pending_scans(task_id, synced);
```

### 2.4 Écrans

1. **Login** : email + mot de passe, token stocké dans expo-secure-store
2. **Dashboard** : stats (pending/in_progress/completed), tâche en cours avec progression, indicateurs sync et connectivité
3. **Liste des tâches** : onglets (En attente | En cours | Terminées), icônes download/sync
4. **Détail tâche** : progression, liste des items avec filtres, boutons action
5. **Scan** : caméra plein écran + NFC toggle, bottom sheet résultat, compteur temps réel

### 2.5 Synchronisation

- Scan → SQLite immédiat → auto-sync si connecté
- Hors ligne → accumulation dans `pending_scans`
- Retour en ligne → NetInfo déclenche auto-sync
- Indicateurs : Synced (vert) / Pending (orange) / Syncing (bleu) / Error (rouge)
- Conflits : le serveur fait foi

### 2.6 Design

- **Primary** : Amber (`#f59e0b`)
- **Success/Warning/Danger** : `#10b981` / `#f97316` / `#ef4444`
- **Background** : `#ffffff`, **Surface** : `#f9fafb`, **Text** : `#111827`
- Feedback immédiat (vibration + son), mode une main, offline-first

---

## Critères d'acceptation

- [ ] Login fonctionnel avec token Sanctum
- [ ] Dashboard avec stats en temps réel et offline
- [ ] Liste des tâches avec indication offline/online
- [ ] Téléchargement d'une tâche pour usage offline
- [ ] Scan barcode (caméra) en mode online et offline
- [ ] Scan NFC en mode online et offline
- [ ] Résolution de barcode : barcode → asset_code → tag_values
- [ ] Ajout d'assets inattendus
- [ ] Complétion de tâche
- [ ] Synchronisation automatique et manuelle
- [ ] Indicateur de sync visible
- [ ] Gestion des conflits de sync
- [ ] Mode hors ligne avec bandeau informatif
- [ ] Compatibilité couleurs/thème avec la plateforme web
