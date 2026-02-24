# Modifications appli mobile — Février 2026

## Priorité haute (changements structurants)

### 1. Nouveaux champs dans les réponses API

**`GET /tasks/{id}/download`** — chaque objet dans `assets[]` contient maintenant :
- `model_name` — nom du modèle (ex: "MacBook Pro 14-inch")
- `model_number` — numéro de modèle (ex: "A2625")
- `supplier_name` — nom du fournisseur

**`POST /tasks/{id}/scan`** — l'objet `asset` contient maintenant :
- `model_name`
- `supplier_name`

**`POST /tasks/{id}/ai-identify`** — chaque match contient maintenant :
- `model_name`

**Action mobile** : mettre à jour les modèles de données locaux (DTOs / classes) pour parser et stocker ces 3 nouveaux champs.

### 2. Résolution hors-ligne multi-identifiants

Le champ unique `barcode` n'existe plus. La résolution d'un code scanné doit maintenant :

```
Pour un code scanné :
1. Vérifier si code === asset_code (ex: "AST-00001")
2. Vérifier si code figure dans tag_values[] de l'asset
   → Chaque tag a: tag_name, value, encoding_mode
3. Si aucun match → asset inconnu / inattendu
```

Les données de `all_asset_barcodes` dans `/download` fournissent :
```json
{
  "asset_id": "...",
  "asset_code": "AST-00001",
  "tag_values": ["FVFXJ3K1Q6LR", "6340971823"]
}
```

### 3. Affichage multi-tags par asset

Un asset peut avoir **plusieurs identifiants** (Serial Number, SKU, tags custom). L'écran de détail asset doit afficher la liste complète des `tag_values[]` avec pour chacun :
- `tag_name` (ex: "Serial Number", "SKU")
- `value` (la valeur scannable)
- `encoding_mode` (qr_code, ean_13, nfc, code_128…)

---

## Priorité moyenne (enrichissement UI)

### 4. Afficher modèle et fournisseur

Sur les écrans de détail asset (résultat de scan, liste d'items, résultat IA) :
- Afficher `model_name` / `model_number` si présent
- Afficher `supplier_name` si présent
- Ces champs sont **optionnels** (nullable)

### 5. Support bounding box pour l'IA

Les endpoints AI acceptent maintenant des paramètres optionnels :
```
bbox_x, bbox_y, bbox_width, bbox_height  (valeurs 0-1)
```
Permet à l'utilisateur de cadrer une zone d'intérêt sur la photo avant envoi.

### 6. Formats de codes-barres multiples

L'app devrait supporter tous les formats d'encodage :
- **2D** : QR Code, Data Matrix, PDF417, Aztec
- **1D** : EAN-13, EAN-8, UPC-A, Code 128, Code 39, ITF
- **Sans fil** : NFC, RFID

---

## Priorité basse (cosmétique)

### 7. Labels UI
- Remplacer toute mention de "Barcode" par "Code" ou "Identifiant"
- Afficher l'icône/badge du type d'encodage à côté de chaque tag

---

## Rétrocompatibilité

- Le paramètre `barcode` de `/scan` fonctionne toujours (le backend cherche dans `asset_code` puis `tag_values`)
- Aucun endpoint n'a été supprimé
- Les nouveaux champs sont **ajoutés**, pas remplacés → pas de breaking change si l'app ignore les champs inconnus
