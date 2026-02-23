# Impact du retrait de `barcode` sur l'API REST et l'application mobile

## Contexte

La colonne `barcode` de la table `assets` est supprimée par la migration `2026_02_23_133106_drop_barcode_from_assets.php`. Le système de code-barres unique par asset est remplacé par le système flexible **AssetTagValue** qui permet plusieurs identifiants par asset (Serial Number, SKU, codes personnalisés) avec différents modes d'encodage (QR Code, EAN-13, NFC, etc.).

---

## 1. Analyse des impacts côté API REST

### Endpoints impactés

| Endpoint | Impact | Détails |
|----------|--------|---------|
| `POST /api/tasks/{taskId}/scan` | **Faible** — Le paramètre s'appelle toujours `barcode` mais la résolution est déjà migrée | Le `InventoryScanService::resolveAsset()` cherche par `asset_code` puis par `tagValues.value` — **ne touche plus la colonne barcode** |
| `GET /api/tasks/{taskId}/download` | **Faible** — La clé `all_asset_barcodes` est conservée pour rétrocompatibilité | Renvoie `asset_id`, `asset_code` et `tag_values[]` — **ne lit plus la colonne barcode** |
| `POST /api/tasks/{taskId}/sync` | **Aucun** — Le champ `identification_method: "barcode"` est un label de méthode, pas un accès à la colonne | Valeurs possibles : `barcode`, `nfc`, `ai_vision`, `manual` |
| `POST /api/tasks/{taskId}/ai-confirm` | **Aucun** | Utilise `identification_method: "ai_vision"` |
| `POST /api/tasks/{taskId}/unexpected` | **Aucun** | Identifie par `asset_id` directement |

### Résolution d'asset — Avant vs Après

**Avant :**
```
barcode → assets.barcode (colonne dédiée)
```

**Après :** (`InventoryScanService::resolveAsset()`)
```
code → assets.asset_code (ex: AST-00001)
   OU → asset_tag_values.value (Serial Number, SKU, code personnalisé)
```

### Points d'attention côté backend

1. **Nommage du paramètre API** : Le endpoint `/scan` accepte toujours `{ "barcode": "..." }` comme paramètre d'entrée. Ce n'est plus techniquement un barcode mais un **code d'identification**. Renommer en `code` serait plus cohérent mais c'est un **breaking change**.

2. **Clé `all_asset_barcodes` dans `/download`** : Même remarque — le nom est trompeur, les données sont correctes (asset_code + tag_values). Un renommage serait un breaking change.

3. **`identification_method: "barcode"` dans `/sync`** : Ce n'est PAS lié à la colonne — c'est un label qui indique que l'asset a été identifié par scan d'un code-barres/QR. À conserver tel quel.

---

## 2. Modifications requises côté application mobile

### A. Aucune modification urgente si l'app utilise déjà l'API actuelle

Le backend a été mis à jour de manière **rétrocompatible** :
- Le paramètre `barcode` dans `/scan` fonctionne toujours (c'est juste un nom de champ)
- La résolution `asset_code → tagValues` est transparente
- `all_asset_barcodes` retourne les données attendues

### B. Modifications recommandées (non urgentes)

| Modification | Priorité | Description |
|-------------|----------|-------------|
| **Résolution offline multi-tags** | Haute | Lors du scan offline, l'app doit chercher dans `asset_code` ET dans `tag_values[]` du payload `/download` (pas seulement un champ `barcode`) |
| **Affichage des tag values** | Moyenne | Afficher les `tag_values` de chaque asset (Serial Number, SKU, etc.) dans le détail d'un asset scanné |
| **Label UI** | Basse | Remplacer les labels "Barcode" par "Code" ou "Identifiant" dans l'interface mobile |
| **Support multi-identifiants** | Basse | Un asset peut maintenant avoir plusieurs identifiants — l'app devrait les afficher tous |

### C. Structure des données `/download` à exploiter

```json
{
  "assets": [
    {
      "id": "...",
      "asset_code": "AST-00001",
      "name": "MacBook Pro",
      "tag_values": [
        { "id": "...", "tag_name": "Serial Number", "value": "FVFXJ3K1Q6LR", "encoding_mode": "qr_code" },
        { "id": "...", "tag_name": "SKU", "value": "6340971823", "encoding_mode": "ean_13" }
      ]
    }
  ],
  "all_asset_barcodes": [
    { "asset_id": "...", "asset_code": "AST-00001", "tag_values": ["FVFXJ3K1Q6LR", "6340971823"] }
  ]
}
```

**Algorithme de résolution offline recommandé :**
1. Scanner un code (QR, barcode, NFC, saisie manuelle)
2. Chercher dans `all_asset_barcodes` :
   - Match par `asset_code` ?
   - Match dans `tag_values[]` ?
3. Si trouvé → associer l'`asset_id`
4. Si non trouvé → asset inattendu ou inconnu

### D. Champs `identification_method` à envoyer au sync

L'app doit correctement renseigner la méthode d'identification lors du `/sync` :
- `"barcode"` → scan d'un code-barres ou QR code
- `"nfc"` → lecture NFC
- `"ai_vision"` → identification par IA (avec `ai_recognition_log_id` et `ai_confidence`)
- `"manual"` → saisie manuelle

---

## 3. Résumé

| Aspect | Impact |
|--------|--------|
| **API backend** | Déjà migré — aucune modification requise pour fonctionner |
| **Contrat API** | Rétrocompatible (noms de champs conservés) |
| **Base de données** | Colonne `barcode` supprimée, remplacée par `asset_tag_values` |
| **App mobile — scan online** | Fonctionne sans changement (le backend résout en interne) |
| **App mobile — scan offline** | Doit utiliser `asset_code` + `tag_values` au lieu d'un champ `barcode` unique |
| **App mobile — UI** | Labels à adapter, affichage des tags multiples à ajouter |
