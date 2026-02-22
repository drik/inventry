# Intégration IA Vision — Application Mobile React Native Expo

## Contexte et Problématique

L'application mobile **Inventry Mobile Scanner** (`rork-inventry-mobile-scanner`) permet aux agents de terrain de scanner des assets par barcode (caméra), NFC/RFID ou saisie manuelle. Cependant, dans de nombreuses situations le scan classique échoue :

- **Étiquette endommagée ou manquante** : le barcode est illisible
- **Asset inconnu** : l'agent ne peut pas identifier l'objet
- **Doute sur l'identité** : l'agent n'est pas sûr de la correspondance

**Objectif** : Implémenter un **mode AI caméra** qui :
1. Détecte et encadre les objets en **temps réel** sur le flux vidéo (on-device, sans réseau)
2. Effectue de l'**OCR** on-device pour lire les textes visibles (numéros de série, étiquettes)
3. Envoie la photo capturée au **backend** pour identification + matching d'assets (Gemini/GPT-4o)
4. Affiche les résultats et permet de confirmer/rejeter la correspondance

---

## Architecture hybride : Edge + Cloud

```
┌─────────────────────────────────────────────────────────┐
│                    Mobile (On-Device)                     │
│                                                           │
│  ┌──────────────────┐    ┌────────────────────────────┐  │
│  │ react-native-    │    │ @react-native-ml-kit/      │  │
│  │ fast-tflite      │    │ text-recognition           │  │
│  │ (SSD MobileNet)  │    │ (ML Kit OCR)               │  │
│  │                  │    │                            │  │
│  │ Object Detection │    │ OCR on-device              │  │
│  │ ~10 FPS temps    │    │ Sur photo capturée         │  │
│  │ réel             │    │                            │  │
│  └───────┬──────────┘    └──────────┬─────────────────┘  │
│          │                          │                     │
│          ▼                          ▼                     │
│  ┌───────────────────────────────────────────────────┐   │
│  │              Skia Overlay (GPU)                     │   │
│  │  Bounding boxes + labels sur flux vidéo            │   │
│  │  Top 2-3 objets premier plan                       │   │
│  └───────────────────────────────────────────────────┘   │
│                          │                                │
│                    [Capture photo]                         │
│                          │                                │
└──────────────────────────┼────────────────────────────────┘
                           │ (réseau requis)
                           ▼
┌──────────────────────────────────────────────────────────┐
│                  Backend Laravel                          │
│                                                           │
│  POST /api/tasks/{taskId}/ai-identify                     │
│  POST /api/tasks/{taskId}/ai-verify                       │
│  POST /api/tasks/{taskId}/ai-confirm                      │
│                                                           │
│  ┌─────────────┐    ┌──────────────┐                     │
│  │ Gemini Flash │    │ GPT-4o       │                     │
│  │ (primaire)   │◄──►│ (fallback)   │                     │
│  └─────────────┘    └──────────────┘                     │
│                                                           │
│  Identification + Matching + Vérification                 │
│  Consomme quota du plan (daily/monthly)                   │
└──────────────────────────────────────────────────────────┘
```

**Edge (on-device)** — Gratuit, sans réseau :
- Détection d'objets temps réel (TFLite SSD MobileNet v2)
- OCR (ML Kit Text Recognition)
- Aide visuelle : l'agent voit les objets détectés avant de capturer

**Cloud (backend)** — Consomme quota, réseau requis :
- Identification précise (catégorie, marque, modèle) via Gemini Flash / GPT-4o
- Matching contre les assets connus de l'organisation
- Vérification d'identité (photo vs asset enregistré)

---

## État actuel du projet mobile

### Projet existant

- **Chemin** : `C:\Users\kodjo\ExpoProjects\rork-inventry-mobile-scanner`
- **Stack** : Expo 54, React Native 0.81.5, TypeScript, Expo Router
- **State** : Zustand + React Query
- **Offline** : AsyncStorage avec sync queue
- **Scan** : `expo-camera` (barcode) + `react-native-nfc-manager` (NFC)

### Bouton AI Mode existant

Le fichier `app/scan/[id].tsx` contient déjà un **bouton AI** (rainbow glow, icône `Sparkles`) qui toggle un mode visuel avec particules sparkle et scan line animée. Ce mode est **purement cosmétique** — il ne fait aucune détection. Il sera remplacé par la navigation vers le nouvel écran AI.

### Code à réutiliser

| Existant | Réutilisation |
|----------|---------------|
| `services/api.ts` | Étendre avec les endpoints AI (identify, verify, confirm) |
| `services/storage.ts` | Étendre avec la file d'attente photos AI |
| `types/inventory.ts` | Étendre avec les types AI |
| `hooks/useNetworkStatus.ts` | Réutiliser tel quel pour détecter online/offline |
| `providers/DataProvider.ts` | Étendre pour le sync des photos AI |
| `constants/colors.ts` | Réutiliser les couleurs |

---

## Nouveaux packages à installer

```bash
# Caméra avancée avec frame processors
npx expo install react-native-vision-camera
npx expo install react-native-worklets-core

# Rendu GPU pour bounding boxes
npx expo install @shopify/react-native-skia

# Détection d'objets TFLite (frame processor plugin)
npx expo install react-native-fast-tflite

# Redimensionnement de frames pour inference
npx expo install vision-camera-resize-plugin

# OCR on-device (ML Kit)
npx expo install @react-native-ml-kit/text-recognition

# Compression photo avant upload
npx expo install expo-image-manipulator

# File system pour stocker photos offline
npx expo install expo-file-system
```

### Configuration Expo (`app.json` — plugins à ajouter)

```json
{
  "plugins": [
    ["react-native-vision-camera", {
      "cameraPermissionText": "Inventry utilise la caméra pour scanner et identifier les assets.",
      "enableMicrophonePermission": false
    }]
  ]
}
```

> **Important** : Le projet utilise déjà `expo-dev-client`. Les nouveaux packages natifs (VisionCamera, Skia, TFLite) nécessitent un rebuild via `npx expo prebuild && npx expo run:android` / `npx expo run:ios`.

### Modèle TFLite

Télécharger **SSD MobileNet v2 COCO** depuis TensorFlow Hub et le placer dans `assets/models/` :

- **Fichier** : `assets/models/ssd_mobilenet_v2.tflite`
- **Taille** : ~6.7 MB
- **Classes** : 80 classes COCO (person, laptop, cell phone, keyboard, monitor, chair, desk, bottle, etc.)
- **Inference** : ~50-100ms sur mid-range, ~20-50ms sur flagship
- **Input** : 300×300 pixels (le resize plugin s'en charge)

---

## Mode AI Caméra — UX détaillé

### Écran AI Camera (`app/ai-scan/[id].tsx`)

```
┌─────────────────────────────────┐
│  ← Retour        IA: 3/5 ⚡     │  ← Header : bouton retour + quota
│                                  │
│  ┌──────────────────────────┐   │
│  │                          │   │
│  │   ┌─── Laptop ────┐     │   │  ← Bounding box vert + label
│  │   │               │     │   │
│  │   │    📷         │     │   │
│  │   │               │     │   │
│  │   └───────────────┘     │   │
│  │                          │   │
│  │      ┌── Phone ──┐      │   │  ← 2ème objet détecté
│  │      │           │      │   │
│  │      └───────────┘      │   │
│  │                          │   │
│  └──────────────────────────┘   │
│                                  │
│  "2 objets détectés"             │  ← Compteur objets
│                                  │
│        ┌────────────┐            │
│        │  📸 Capture │            │  ← Bouton capture central
│        └────────────┘            │
│                                  │
│   ⚡ Flash    🔄 Retourner       │  ← Contrôles caméra
└─────────────────────────────────┘
```

### Flux utilisateur

1. **Entrée** : L'agent appuie sur le bouton AI (sparkle) dans l'écran de scan
2. **Navigation** : `router.push(`/ai-scan/${taskId}`)` → nouvel écran VisionCamera
3. **Détection temps réel** :
   - VisionCamera capture le flux vidéo
   - Frame processor traite 1 frame sur 3 (~10 détections/seconde)
   - TFLite SSD MobileNet détecte les objets
   - Filtrage : top 2-3 objets au premier plan
   - Skia dessine les bounding boxes + labels sur le preview
4. **Capture** : L'agent appuie sur le bouton central
   - Photo haute résolution capturée
   - OCR ML Kit extrait les textes visibles
   - Prévisualisation avec "Utiliser" / "Reprendre"
5. **Upload** : Photo compressée (JPEG 85%, max 1024px) envoyée au backend
   - Loading spinner pendant l'analyse (1-4 secondes)
   - Indicateur du provider utilisé (Gemini/GPT-4o)
6. **Résultats** : Bottom sheet avec identification + matches
7. **Action** : Confirmer / Ajouter inattendu / Annuler

### Filtrage des objets (premier plan uniquement)

```typescript
function filterForegroundObjects(
  detections: Detection[],
  frameWidth: number,
  frameHeight: number
): Detection[] {
  const frameArea = frameWidth * frameHeight;

  return detections
    // 1. Confiance suffisante
    .filter(d => d.confidence >= 0.5)
    // 2. Taille raisonnable (pas trop petit = fond, pas trop grand = background)
    .filter(d => {
      const area = d.width * d.height;
      const ratio = area / frameArea;
      return ratio >= 0.03 && ratio <= 0.85;
    })
    // 3. Tri par surface décroissante (plus gros = plus proche = premier plan)
    .sort((a, b) => (b.width * b.height) - (a.width * a.height))
    // 4. Garder les 2-3 plus gros
    .slice(0, 3);
}
```

### Couleurs des bounding boxes

| Rang | Couleur | Usage |
|------|---------|-------|
| 1er objet (plus gros) | `#10b981` (vert) | Objet principal |
| 2ème objet | `#3b82f6` (bleu) | Objet secondaire |
| 3ème objet | `#f97316` (orange) | Objet tertiaire |

### Labels affichés

Les labels COCO sont traduits en français pour les catégories pertinentes à l'inventaire :

```typescript
const COCO_LABELS_FR: Record<string, string> = {
  'laptop': 'Ordinateur portable',
  'cell phone': 'Téléphone',
  'keyboard': 'Clavier',
  'mouse': 'Souris',
  'monitor': 'Écran',
  'tv': 'Écran/TV',
  'chair': 'Chaise',
  'couch': 'Canapé',
  'desk': 'Bureau',
  'book': 'Livre',
  'bottle': 'Bouteille',
  'cup': 'Tasse',
  'refrigerator': 'Réfrigérateur',
  'microwave': 'Micro-ondes',
  'printer': 'Imprimante',
  // ... autres catégories pertinentes
};
```

Format d'affichage : `"Ordinateur portable  87%"` (label + confiance)

---

## Intégration dans le flux de scan existant

```
Écran Scan existant (app/scan/[id].tsx)
  │
  ├── Scan barcode/NFC → flux existant inchangé
  │
  └── Bouton AI (Sparkles) → MODIFIÉ
        │
        └── router.push(`/ai-scan/${id}`)
              │
              ├── Vue VisionCamera avec détection temps réel
              │   └── Bounding boxes + labels sur les objets
              │
              ├── [Capture photo]
              │   ├── OCR ML Kit (textes détectés localement)
              │   └── POST /api/tasks/{taskId}/ai-identify
              │       └── Résultats dans AiResultsSheet :
              │
              ├── Correspondance(s) trouvée(s)
              │   └── Liste AiMatchCard (image, nom, code, confiance)
              │       └── Agent sélectionne → POST /ai-confirm (matched)
              │           → Item marqué Found (identification_method = ai_vision)
              │           → Retour à l'écran Scan
              │
              ├── Pas de correspondance satisfaisante
              │   └── "Ajouter comme inattendu"
              │       → POST /ai-confirm (unexpected)
              │       → Retour à l'écran Scan
              │
              └── Annuler
                  → POST /ai-confirm (dismissed)
                  → Retour à l'écran Scan
```

### Bouton "Vérifier par photo" (optionnel)

Après un scan barcode réussi, un petit bouton "Vérifier" permet de comparer la photo de l'objet physique avec l'image enregistrée de l'asset :

```
Scan barcode → Asset trouvé
  └── [Vérifier par photo]
        → AiCameraScreen (mode verify)
        → POST /api/tasks/{taskId}/ai-verify
        → Résultat : "Correspond ✓" ou "Ne correspond pas ✗"
```

---

## Endpoints API backend (déjà implémentés)

### `POST /api/tasks/{taskId}/ai-identify`

**Middleware** : `auth:sanctum`, `throttle:ai-vision`, `plan.limit:max_ai_requests_daily`

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
      "reasoning": "Le modèle et la couleur correspondent.",
      "inventory_status": "expected"
    }
  ],
  "has_strong_match": true,
  "usage": {
    "daily": { "current": 3, "limit": 5, "remaining": 2 },
    "monthly": { "current": 28, "limit": 50, "remaining": 22 }
  }
}
```

**Response 403** (quota dépassé) :
```json
{
  "message": "Limite atteinte : votre plan Basic autorise 5 Requêtes IA / jour.",
  "error": "plan_limit_reached",
  "feature": "max_ai_requests_daily"
}
```

### `POST /api/tasks/{taskId}/ai-verify`

**Request** : `multipart/form-data`
- `photo` : fichier image
- `asset_id` : ULID de l'asset à vérifier

**Response 200** :
```json
{
  "recognition_log_id": "01JN...",
  "is_match": true,
  "confidence": 0.94,
  "reasoning": "L'appareil photographié correspond à l'image de référence.",
  "discrepancies": [],
  "usage": { "daily": {...}, "monthly": {...} }
}
```

### `POST /api/tasks/{taskId}/ai-confirm`

**Request** : `application/json`
```json
{
  "recognition_log_id": "01JN...",
  "asset_id": "01JN...",
  "action": "matched"
}
```

- `action` : `matched` | `unexpected` | `dismissed`

**Response 200** :
```json
{
  "action": "matched",
  "item": {
    "id": "01JN...",
    "asset_id": "01JN...",
    "status": "found",
    "scanned_at": "2026-02-22T14:30:00Z",
    "identification_method": "ai_vision"
  }
}
```

---

## Gestion offline

### Capacités offline vs online

| Fonctionnalité | Online | Offline |
|----------------|--------|---------|
| Détection objets temps réel (TFLite) | ✅ | ✅ |
| OCR on-device (ML Kit) | ✅ | ✅ |
| Bounding boxes + labels | ✅ | ✅ |
| Identification cloud (Gemini/GPT-4o) | ✅ | ❌ |
| Matching assets | ✅ | ❌ |
| File d'attente photos | — | ✅ |
| Sync auto au retour réseau | ✅ | — |

### Mode dégradé offline

1. L'agent active le mode AI → détection temps réel fonctionne normalement
2. L'agent capture une photo → OCR on-device extrait les textes
3. Si un texte OCR correspond à un barcode/asset_code dans `taskData.all_asset_barcodes` → résolution locale immédiate
4. Sinon → photo stockée dans la file d'attente avec badge "📷 1 photo en attente"
5. Au retour du réseau → envoi automatique, affichage du résultat
6. Si quota épuisé au moment du sync → photo reste en attente, notification

### Stockage local des photos en attente

Utiliser `expo-file-system` pour stocker les photos et `AsyncStorage` pour les métadonnées :

```typescript
// Clé AsyncStorage
const KEYS = {
  pendingAiPhotos: (taskId: string) => `inventry_pending_ai_photos_${taskId}`,
};

interface PendingAiPhoto {
  id: string;                    // UUID généré côté mobile
  taskId: string;
  photoUri: string;              // Chemin local (FileSystem.documentDirectory)
  ocrText: string[];             // Textes détectés par OCR on-device
  resolvedAssetId: string | null; // Si OCR a résolu un asset localement
  edgeDetections: EdgeDetection[]; // Objets détectés on-device (label + confiance)
  status: 'pending' | 'uploading' | 'completed' | 'failed' | 'quota_exceeded';
  createdAt: string;
  syncedAt: string | null;
  apiResponse: AiIdentifyResponse | null;
}

interface EdgeDetection {
  label: string;
  confidence: number;
  boundingBox: { x: number; y: number; width: number; height: number };
}
```

---

## Nouveaux fichiers à créer

### Structure dans le projet RN

```
rork-inventry-mobile-scanner/
├── app/
│   └── ai-scan/
│       └── [id].tsx                  # Écran caméra AI (VisionCamera + overlay)
├── components/
│   └── ai/
│       ├── AiResultsSheet.tsx        # Bottom sheet résultats IA
│       ├── AiMatchCard.tsx           # Carte match individuelle
│       ├── AiIdentificationHeader.tsx # En-tête identification (catégorie, marque)
│       ├── AiQuotaIndicator.tsx      # Indicateur quota IA (daily/monthly)
│       ├── AiVerifyResult.tsx        # Résultat de vérification
│       └── AiPendingBadge.tsx        # Badge "X photos en attente"
├── hooks/
│   ├── useObjectDetection.ts         # Hook frame processor TFLite
│   ├── useOcr.ts                     # Hook OCR ML Kit
│   └── useAiIdentify.ts             # Hook flux identification complet
├── services/
│   └── aiVision.ts                   # Appels API AI (identify, verify, confirm)
├── types/
│   └── aiVision.ts                   # Types TypeScript pour l'IA
├── utils/
│   └── cocoLabels.ts                 # Labels COCO traduits en français
└── assets/
    └── models/
        └── ssd_mobilenet_v2.tflite   # Modèle TFLite (~6.7 MB)
```

### Fichiers existants à modifier

| Fichier | Modification |
|---------|-------------|
| `app/scan/[id].tsx` | Remplacer le toggle AI cosmétique par `router.push(`/ai-scan/${id}`)` |
| `services/api.ts` | Ajouter les méthodes `ai.identify()`, `ai.verify()`, `ai.confirm()` |
| `services/storage.ts` | Ajouter gestion `pending_ai_photos` |
| `types/inventory.ts` | Ajouter champs `identification_method`, `ai_confidence` à `InventoryItem` et `SyncPayload` |
| `app.json` | Ajouter le plugin `react-native-vision-camera` |
| `package.json` | Ajouter les nouvelles dépendances |

---

## Détail des composants clés

### `app/ai-scan/[id].tsx` — Écran caméra AI

```tsx
// Structure simplifiée
export default function AiScanScreen() {
  const { id: taskId } = useLocalSearchParams();
  const device = useCameraDevice('back');
  const { detections } = useObjectDetection();

  const frameProcessor = useSkiaFrameProcessor((frame) => {
    'worklet';
    // 1. Render camera frame
    frame.render();

    // 2. Resize pour TFLite (300x300)
    const resized = resize(frame, { width: 300, height: 300 });

    // 3. Détection TFLite
    const results = model.runForMultipleOutputs([resized]);

    // 4. Filtrage premier plan (top 2-3)
    const filtered = filterForeground(results, frame.width, frame.height);

    // 5. Dessiner bounding boxes Skia
    for (const detection of filtered) {
      const rect = Skia.XYWHRect(detection.x, detection.y, detection.w, detection.h);
      frame.drawRect(rect, boxPaint);
      frame.drawText(detection.label, detection.x, detection.y - 8, textPaint, font);
    }
  }, [model]);

  const handleCapture = async () => {
    const photo = await camera.current.takePhoto({ flash: 'off' });
    // OCR + upload
  };

  return (
    <Camera
      ref={camera}
      device={device}
      isActive={true}
      photo={true}
      frameProcessor={frameProcessor}
      pixelFormat="rgb"
    />
  );
}
```

### `hooks/useObjectDetection.ts`

```typescript
import { useTensorflowModel } from 'react-native-fast-tflite';

export function useObjectDetection() {
  const model = useTensorflowModel(
    require('@/assets/models/ssd_mobilenet_v2.tflite')
  );

  // Le modèle est chargé de manière asynchrone
  // Retourne null pendant le chargement
  return {
    model: model.state === 'loaded' ? model.model : null,
    isLoading: model.state === 'loading',
    error: model.state === 'error' ? model.error : null,
  };
}
```

### `hooks/useOcr.ts`

```typescript
import TextRecognition from '@react-native-ml-kit/text-recognition';

export function useOcr() {
  const recognizeText = async (imageUri: string): Promise<string[]> => {
    const result = await TextRecognition.recognize(imageUri);
    // Extraire les textes pertinents (numéros de série, codes)
    return result.blocks
      .flatMap(block => block.lines)
      .map(line => line.text)
      .filter(text => text.length >= 3); // Ignorer les textes trop courts
  };

  return { recognizeText };
}
```

### `services/aiVision.ts`

```typescript
import { getBaseUrl, getToken } from './api';

export const aiVisionApi = {
  identify: async (taskId: string, photoUri: string): Promise<AiIdentifyResponse> => {
    const token = await getToken();
    const formData = new FormData();
    formData.append('photo', {
      uri: photoUri,
      type: 'image/jpeg',
      name: 'capture.jpg',
    } as any);

    const response = await fetch(`${getBaseUrl()}/api/tasks/${taskId}/ai-identify`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
      body: formData,
    });

    if (!response.ok) {
      const error = await response.json();
      throw new AiVisionError(response.status, error);
    }

    return response.json();
  },

  verify: async (taskId: string, photoUri: string, assetId: string): Promise<AiVerifyResponse> => {
    // ... similaire à identify avec asset_id en plus
  },

  confirm: async (taskId: string, recognitionLogId: string, assetId: string | null, action: string): Promise<AiConfirmResponse> => {
    // ... POST JSON (pas multipart)
  },
};
```

### `components/ai/AiResultsSheet.tsx`

Bottom sheet qui s'affiche après réception des résultats du backend :

```
┌─────────────────────────────────────┐
│  ────  (drag handle)                │
│                                     │
│  🔍 Identification                  │
│  ┌─────────────────────────────────┐│
│  │ Ordinateur portable             ││
│  │ Dell Latitude 5540              ││
│  │ Confiance : 92%  ██████████░░   ││
│  │ Textes : SN: ABCD-1234-EFGH    ││
│  └─────────────────────────────────┘│
│                                     │
│  📋 Correspondances (2)            │
│  ┌─────────────────────────────────┐│
│  │ 🖼 Dell Latitude 5540           ││
│  │    AST-00003 · Siège - Lomé     ││
│  │    89% ████████░░   🟢 Attendu  ││
│  │    [Sélectionner]               ││
│  └─────────────────────────────────┘│
│  ┌─────────────────────────────────┐│
│  │ 🖼 HP EliteBook 840            ││
│  │    AST-00015 · Bureau 3         ││
│  │    45% ████░░░░░░               ││
│  │    [Sélectionner]               ││
│  └─────────────────────────────────┘│
│                                     │
│  [✓ Confirmer]  [+ Inattendu]  [✗] │
│                                     │
│  IA: 2/5 aujourd'hui · 22/50 /mois │
└─────────────────────────────────────┘
```

### `components/ai/AiQuotaIndicator.tsx`

```
┌──────────────────────────┐
│  ⚡ IA: 3/5 aujourd'hui  │
│  ████████░░░░  60%       │
└──────────────────────────┘
```

Affiché en haut de l'écran AI camera. Couleurs :
- Vert (< 70%) → il reste du quota
- Orange (70-90%) → quota se remplit
- Rouge (> 90%) → quota presque épuisé

---

## Types TypeScript

### `types/aiVision.ts`

```typescript
// ── Détection on-device (TFLite) ──
export interface EdgeDetection {
  label: string;           // Label COCO (ex: "laptop")
  labelFr: string;         // Label traduit (ex: "Ordinateur portable")
  confidence: number;      // 0-1
  boundingBox: BoundingBox;
}

export interface BoundingBox {
  x: number;      // Coordonnée X (pixels)
  y: number;      // Coordonnée Y (pixels)
  width: number;  // Largeur (pixels)
  height: number; // Hauteur (pixels)
}

// ── Réponses API backend ──
export interface AiIdentifyResponse {
  recognition_log_id: string;
  identification: AiIdentification;
  matches: AiMatch[];
  has_strong_match: boolean;
  usage: AiUsage;
}

export interface AiIdentification {
  suggested_category: string | null;
  suggested_brand: string | null;
  suggested_model: string | null;
  detected_text: string[];
  confidence: number;
  description: string | null;
}

export interface AiMatch {
  asset_id: string;
  asset_name: string;
  asset_code: string;
  category_name: string | null;
  location_name: string | null;
  primary_image_url: string | null;
  confidence: number;
  reasoning: string;
  inventory_status: 'expected' | 'found' | 'missing' | 'unexpected' | null;
}

export interface AiVerifyResponse {
  recognition_log_id: string;
  is_match: boolean;
  confidence: number;
  reasoning: string;
  discrepancies: string[];
  usage: AiUsage;
}

export interface AiConfirmResponse {
  action: 'matched' | 'unexpected' | 'dismissed';
  item: {
    id: string;
    asset_id: string;
    status: string;
    scanned_at: string;
    identification_method: string;
  } | null;
}

export interface AiUsage {
  daily: AiQuota;
  monthly: AiQuota;
}

export interface AiQuota {
  current: number;
  limit: number;
  remaining: number;
}

// ── Photos en attente (offline) ──
export interface PendingAiPhoto {
  id: string;
  taskId: string;
  photoUri: string;
  ocrText: string[];
  resolvedAssetId: string | null;
  edgeDetections: EdgeDetection[];
  status: 'pending' | 'uploading' | 'completed' | 'failed' | 'quota_exceeded';
  createdAt: string;
  syncedAt: string | null;
  apiResponse: AiIdentifyResponse | null;
}

// ── Erreur API IA ──
export class AiVisionError extends Error {
  status: number;
  data: {
    message: string;
    error: string;
    feature?: string;
  };

  constructor(status: number, data: any) {
    super(data.message || 'AI Vision Error');
    this.status = status;
    this.data = data;
  }

  get isQuotaExceeded(): boolean {
    return this.status === 403 && this.data.error === 'plan_limit_reached';
  }
}
```

---

## Modification du SyncPayload

Le `SyncPayload` existant doit accepter les nouveaux champs IA. Modifier `types/inventory.ts` :

```typescript
export interface SyncPayload {
  scans: Array<{
    item_id: string | null;
    asset_id?: string;
    status: string;
    scanned_at: string;
    condition_notes: string | null;
    // Nouveaux champs IA
    identification_method?: 'barcode' | 'nfc' | 'ai_vision' | 'manual';
    ai_recognition_log_id?: string;
    ai_confidence?: number;
  }>;
  task_status: TaskStatus;
  task_notes: string | null;
  last_synced_at: string | null;
}
```

---

## Gestion du quota épuisé côté mobile

Quand le backend retourne 403 (`plan_limit_reached`) :

```tsx
// Dans AiScanScreen ou AiResultsSheet
if (error instanceof AiVisionError && error.isQuotaExceeded) {
  Alert.alert(
    'Quota IA épuisé',
    error.data.message, // "Votre plan Basic autorise 5 requêtes IA par jour."
    [
      { text: 'Voir les plans', onPress: () => {
        // Ouvrir la page subscription dans le navigateur web
        Linking.openURL(`${getBaseUrl()}/app/subscription`);
      }},
      { text: 'Fermer', style: 'cancel' },
    ]
  );
}
```

---

## Phases d'implémentation

### Phase 1 : Setup packages + modèle TFLite (2-3 jours)

1. Installer les packages (`react-native-vision-camera`, `react-native-fast-tflite`, `@shopify/react-native-skia`, `react-native-worklets-core`, `vision-camera-resize-plugin`, `@react-native-ml-kit/text-recognition`, `expo-image-manipulator`, `expo-file-system`)
2. Configurer `app.json` (plugin VisionCamera)
3. `npx expo prebuild` + rebuild natif
4. Télécharger et placer `ssd_mobilenet_v2.tflite` dans `assets/models/`
5. Créer `types/aiVision.ts` avec tous les types
6. Créer `utils/cocoLabels.ts` avec la map de traduction
7. Tester que VisionCamera + TFLite se chargent sans crash

### Phase 2 : Frame processor + détection temps réel + overlay Skia (3-4 jours)

1. Créer `hooks/useObjectDetection.ts` (chargement modèle TFLite)
2. Créer `app/ai-scan/[id].tsx` avec VisionCamera
3. Implémenter le `useSkiaFrameProcessor` :
   - Resize frame à 300×300 via `vision-camera-resize-plugin`
   - Inference TFLite
   - Parsing des résultats SSD MobileNet (bounding boxes + classes + scores)
   - Filtrage premier plan (confidence >= 0.5, surface >= 3%, top 3)
   - Rendu Skia : rectangles colorés + labels
4. Ajouter contrôles caméra (flash, retourner)
5. Ajouter compteur d'objets détectés
6. Tester sur appareil réel (pas d'émulateur pour la caméra)

### Phase 3 : Capture photo + OCR + envoi backend (2-3 jours)

1. Implémenter la capture photo (`camera.takePhoto()`)
2. Compression avec `expo-image-manipulator` (JPEG 85%, max 1024px)
3. Créer `hooks/useOcr.ts` (ML Kit Text Recognition)
4. Appliquer OCR sur la photo capturée
5. Créer `services/aiVision.ts` (appels API multipart/form-data)
6. Modifier `services/api.ts` pour intégrer les endpoints AI
7. Créer `components/ai/AiQuotaIndicator.tsx`
8. Implémenter le flux : capture → OCR → upload → loading → résultats
9. Gérer les erreurs (réseau, quota, timeout)

### Phase 4 : Bottom sheet résultats + flux confirmer/rejeter (2-3 jours)

1. Créer `components/ai/AiIdentificationHeader.tsx`
2. Créer `components/ai/AiMatchCard.tsx`
3. Créer `components/ai/AiResultsSheet.tsx`
4. Créer `components/ai/AiVerifyResult.tsx`
5. Créer `hooks/useAiIdentify.ts` (orchestration complète du flux)
6. Implémenter les actions : Confirmer (matched) / Inattendu (unexpected) / Annuler (dismissed)
7. Après confirmation → retour à l'écran scan avec mise à jour de la liste
8. Modifier `app/scan/[id].tsx` : remplacer AI cosmétique par navigation vers `/ai-scan/[id]`
9. Feedback haptique et sonore
10. Modifier `types/inventory.ts` pour les champs AI dans `SyncPayload`

### Phase 5 : Offline queue + sync auto + finitions (2-3 jours)

1. Modifier `services/storage.ts` : ajouter `pending_ai_photos` storage
2. Créer `components/ai/AiPendingBadge.tsx` (badge photos en attente)
3. Implémenter la résolution locale OCR → barcode_index
4. Implémenter la file d'attente offline :
   - Stocker photo + OCR + edge detections dans FileSystem + AsyncStorage
   - Au retour réseau : upload auto séquentiel
   - Gestion statut `quota_exceeded`
5. Modifier `providers/DataProvider.ts` : ajouter sync des photos AI
6. Tester mode avion complet
7. Polishing UX : animations, transitions, edge cases

**Effort total estimé : 11-16 jours** pour un développeur.

---

## Vérification

### Tests on-device (Edge)
1. Ouvrir l'écran AI → vérifier que la caméra VisionCamera s'affiche
2. Pointer un objet (laptop, téléphone) → vérifier bounding box + label en temps réel
3. Vérifier que seuls 2-3 objets au premier plan sont encadrés
4. Vérifier les FPS (devrait être fluide, ~10 détections/s)
5. Capturer une photo → vérifier que l'OCR extrait les textes

### Tests API (Cloud)
6. Capturer une photo d'un asset → vérifier l'envoi et la réponse (`/ai-identify`)
7. Vérifier l'indicateur de quota (ex: "3/5 aujourd'hui")
8. Sélectionner une correspondance → vérifier que l'item est marqué Found avec `identification_method = ai_vision`
9. Ajouter comme inattendu → vérifier la création de l'item Unexpected
10. Annuler → vérifier que rien ne change

### Tests quota
11. Épuiser le quota quotidien → vérifier l'alerte "Quota IA épuisé" avec bouton "Voir les plans"
12. Le lendemain → vérifier que le quota est réinitialisé

### Tests vérification
13. Scanner un asset par barcode → "Vérifier par photo" → capturer → vérifier le résultat

### Tests offline
14. Passer en mode avion → mode AI → détection fonctionne toujours
15. Capturer une photo en offline → vérifier le stockage local + badge "1 photo en attente"
16. Réactiver le réseau → vérifier l'envoi automatique et l'affichage du résultat
17. Vérifier le retour sur l'écran Scan avec la liste mise à jour

### Tests dans Filament (backend)
18. Vérifier que l'`InventoryItem` montre `identification_method = ai_vision`
19. Vérifier les logs dans `ai_recognition_logs` (provider, tokens, cost, latency)
20. Vérifier les compteurs IA dans `ai_usage_logs`
