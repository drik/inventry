# Estimation : Migration Filament v4 → Laravel + TailAdmin

## Contexte

Estimer le temps et la complexité de réécriture de toutes les fonctionnalités actuellement développées avec Filament v4 en Laravel pur avec TailAdmin comme base UI.

**Point clé** : Filament est un **framework CRUD complet** (forms, tables, filters, relations, multi-tenancy auto-gérés). TailAdmin est **uniquement un template UI** (composants Tailwind CSS). Tout le back-end, la logique CRUD, la validation, le filtrage, la pagination, la recherche doivent être codés manuellement.

---

## Inventaire de l'existant Filament

| Élément | Quantité |
|---------|----------|
| Modèles Eloquent | 13 |
| Migrations / Tables | 18 |
| Enums | 3 (18 cases) |
| Resources Filament (CRUD complet) | 6 |
| Pages Resource (List/Create/Edit) | 18 |
| Relation Managers | 2 |
| Pages Custom (invitations, org) | 3 |
| Champs de formulaire (total) | ~60 |
| Colonnes de table (total) | ~47 |
| Filtres | ~12 |
| Actions custom | 3 (assign, invite, inline tag creation) |
| Relations Eloquent | 25+ |
| Colonnes DB (total) | ~120+ |

---

## Estimation par module

### Légende complexité
- 🟢 Simple : CRUD basique, peu de champs
- 🟡 Moyen : Relations, validation, logique métier
- 🔴 Complexe : Multi-tenancy, polymorphisme, logique avancée

---

### 1. Infrastructure & Multi-tenancy 🔴

| Tâche | Détail |
|-------|--------|
| Middleware tenant | Identifier l'organisation courante, scope global |
| Trait BelongsToOrganization | Réécrire le scope + auto-assign (existe déjà) |
| Auth (login, register) | Controllers + vues Blade TailAdmin |
| Sélection de tenant | UI switch d'organisation |
| Org registration + profil | Controllers + formulaires |
| Système d'invitations | Controller, modèle, email, vues |

**Estimation** : 3-4 jours | **Complexité** : 🔴

---

### 2. Base CRUD partagée

Pour chaque module CRUD, il faut créer manuellement :
- **Controller** (index, create, store, edit, update, destroy) — ~150-200 lignes
- **Form Request** (validation) — ~30-50 lignes
- **Vues Blade** (list, create, edit) avec composants TailAdmin — ~200-400 lignes/vue
- **Routes** (web.php) — ~5-10 lignes
- **JS/Alpine** pour interactivité (filtres, recherche, modales) — variable

---

### 3. AssetCategory 🟢

| Composant | Filament (actuel) | TailAdmin (à coder) |
|-----------|-------------------|---------------------|
| Formulaire | 7 champs déclaratifs | Controller + 3 vues Blade + validation |
| Table | 7 colonnes, 1 filtre, tri, search | Vue Blade + pagination + tri JS/backend |
| Hiérarchie parent/enfant | Select relationship auto | Query manuelle + select peuplé |
| Soft deletes + restore | 3 bulk actions déclaratives | Logique controller + UI |

**Estimation** : 1.5-2 jours | **Complexité** : 🟢

---

### 4. Manufacturer 🟢

| Composant | Filament (actuel) | TailAdmin (à coder) |
|-----------|-------------------|---------------------|
| Formulaire | 5 champs | Controller + vues + validation |
| Table | 5 colonnes, liens URL, count relation | Vue Blade + pagination |

**Estimation** : 1 jour | **Complexité** : 🟢

---

### 5. AssetModel 🟢

| Composant | Filament (actuel) | TailAdmin (à coder) |
|-----------|-------------------|---------------------|
| Formulaire | 6 champs dont FileUpload | Controller + vues + gestion upload image |
| Table | 5 colonnes avec relations | Vue Blade + pagination |
| Select dynamiques | manufacturer, category auto-peuplés | Requêtes manuelles |

**Estimation** : 1.5 jours | **Complexité** : 🟢

---

### 6. Location 🟡

| Composant | Filament (actuel) | TailAdmin (à coder) |
|-----------|-------------------|---------------------|
| Formulaire | 8 champs en 3 sections | Controller + vues + validation |
| Table | 6 colonnes, count relation | Vue Blade + pagination |
| Hiérarchie parent/enfant | Auto via Select | Query manuelle |
| Soft deletes | Déclaratif | Logique manuelle |

**Estimation** : 1.5 jours | **Complexité** : 🟡

---

### 7. Department 🟡

| Composant | Filament (actuel) | TailAdmin (à coder) |
|-----------|-------------------|---------------------|
| Formulaire | 4 selects avec relations | Controller + vues + validation |
| Table | 6 colonnes, hiérarchie | Vue Blade + pagination |

**Estimation** : 1 jour | **Complexité** : 🟡

---

### 8. Asset 🔴🔴 (le plus complexe)

| Composant | Filament (actuel) | TailAdmin (à coder) |
|-----------|-------------------|---------------------|
| Formulaire 4 onglets | 25+ champs déclaratifs | 4 vues partielles Blade + JS onglets |
| Auto-génération code/barcode | Boot model (existe) | Existe déjà dans le modèle |
| Select dynamiques | 5 relationships auto | Requêtes manuelles, AJAX |
| Repeater images | Relationship repeater grid 4 | Upload multiple custom + JS réordonnement + CRUD AJAX |
| Tags MorphToMany | Select multiple + inline create | UI custom + AJAX creation + gestion pivot |
| RichEditor | Composant déclaratif | Intégration TinyMCE/Trix |
| Table | 11 colonnes, 5 filtres, tri | Vue Blade complexe + filtres backend + pagination |
| Action "Assigner" | Modale déclarative + logique | Modale TailAdmin + controller AJAX + logique status |
| Soft deletes + restore | Déclaratif | Logique manuelle |
| Warranty color danger | Inline closure | Logique Blade conditionnelle |

**Estimation** : 5-7 jours | **Complexité** : 🔴🔴

---

### 9. AssetImage (via Asset) 🟡

| Composant | Filament (actuel) | TailAdmin (à coder) |
|-----------|-------------------|---------------------|
| Upload multiple | Repeater relationship | Dropzone/FilePond + controller upload |
| Réordonnement drag & drop | orderColumn déclaratif | SortableJS + AJAX endpoint |
| Image principale toggle | Toggle dans repeater | UI custom + AJAX |
| Grille 4 colonnes | grid(4) | CSS Grid TailAdmin |

**Estimation** : 2 jours (inclus dans Asset) | **Complexité** : 🟡

---

### 10. AssetTag 🟡

| Composant | Filament (actuel) | TailAdmin (à coder) |
|-----------|-------------------|---------------------|
| Select multiple + création inline | createOptionForm déclaratif | Select2/Choices.js + modale création AJAX |
| Types (QR, NFC, RFID, 1D) | Enum dans Select | Enum existe, UI manuelle |

**Estimation** : 1 jour (inclus dans Asset) | **Complexité** : 🟡

---

### 11. AssetStatusHistory 🟡

| Composant | Filament (actuel) | TailAdmin (à coder) |
|-----------|-------------------|---------------------|
| Relation Manager (read-only) | 5 colonnes déclaratives | Vue Blade partielle sur page Asset |
| Badges colorés par enum | Auto via HasColor | Classes CSS conditionnelles |

**Estimation** : 0.5 jour | **Complexité** : 🟡

---

### 12. Assignment 🔴

| Composant | Filament (actuel) | TailAdmin (à coder) |
|-----------|-------------------|---------------------|
| Formulaire polymorphique | Select dynamique via Get | Alpine.js + AJAX pour charger les options |
| Section Retour (edit only) | visibleOn('edit') | Logique Blade conditionnelle |
| Table | 7 colonnes, 2 filtres, tri | Vue Blade + pagination + filtres |
| Ternary filter actif/retourné | Déclaratif | Query params + logique controller |
| Relation Manager dans Asset | Déclaratif | Tab/section dans la page Asset |
| Badge type polymorphique | formatStateUsing | Helper/Accessor Blade |
| Color danger si retard | Inline closure | Logique Blade conditionnelle |

**Estimation** : 3-4 jours | **Complexité** : 🔴

---

## Résumé global

| Module | Jours estimés | Complexité |
|--------|--------------|------------|
| Infrastructure & Multi-tenancy | 3-4 j | 🔴 |
| AssetCategory | 1.5-2 j | 🟢 |
| Manufacturer | 1 j | 🟢 |
| AssetModel | 1.5 j | 🟢 |
| Location | 1.5 j | 🟡 |
| Department | 1 j | 🟡 |
| Asset (+ Images + Tags) | 5-7 j | 🔴🔴 |
| AssetStatusHistory | 0.5 j | 🟡 |
| Assignment | 3-4 j | 🔴 |
| Tests & corrections | 2-3 j | 🟡 |
| **TOTAL** | **~20-28 jours** | |

---

## Comparaison effort

| Métrique | Filament v4 | Laravel + TailAdmin |
|----------|-------------|---------------------|
| Temps développement initial | ~3-5 jours | ~20-28 jours |
| Fichiers PHP à écrire | ~50 (déclaratifs) | ~150+ (controllers, requests, vues) |
| Vues Blade | 0 (auto-générées) | ~40-50 fichiers |
| JavaScript custom | 0 | ~15-20 composants Alpine.js |
| Maintenance long terme | Mises à jour Filament | Maintenance entière du code UI |
| Facteur multiplicateur | 1x | **5-6x** |

---

## Conclusion

La réécriture en Laravel + TailAdmin représente environ **20-28 jours de travail** (1 développeur), soit **5-6x plus** que ce qui a été réalisé avec Filament v4. Le module Asset seul représente 30-40% de l'effort total à cause de sa complexité (onglets, images, tags, assignation, historique).

TailAdmin apporte les composants UI mais **aucune logique CRUD** — chaque formulaire, table, filtre, pagination, recherche et action doit être codé manuellement avec des controllers Laravel, des vues Blade et du JavaScript Alpine.js.
