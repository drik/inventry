# Plan : Remplacer le layout Filament par TailAdmin

## Contexte

Le layout par défaut de Filament (sidebar grise, topbar simple) doit être remplacé par le layout TailAdmin (sidebar dark avec collapse/expand, topbar avec dark mode toggle/user dropdown, transitions fluides).

**Approche** : Override les vues Blade vendor de Filament (Livewire sidebar/topbar + layout index) pour y injecter le HTML/CSS/Alpine.js TailAdmin, tout en conservant la logique Livewire de Filament (navigation, formulaires, tables, modales, etc.).

**Avantage** : Filament utilise déjà `$store.sidebar` avec `isOpen`, `open()`, `close()` et `$store.theme`. On réutilise ces stores existants.

---

## Fichiers à créer/modifier

| # | Fichier | Action | Description |
|---|---------|--------|-------------|
| 1 | `resources/views/vendor/filament/livewire/sidebar.blade.php` | Créer (override) | Sidebar TailAdmin avec nav Filament |
| 2 | `resources/views/vendor/filament/livewire/topbar.blade.php` | Créer (override) | Topbar TailAdmin avec user menu + dark mode |
| 3 | `resources/views/vendor/filament/components/layout/index.blade.php` | Créer (override) | Structure wrapper TailAdmin (widths, positioning) |
| 4 | `resources/css/filament/app/theme.css` | Modifier | Ajouter classes TailAdmin + overrides fi-* |
| 5 | `app/Providers/Filament/AppPanelProvider.php` | Modifier | Activer sidebarCollapsibleOnDesktop + darkMode + sidebarWidth/collapsedSidebarWidth |

---

## Étapes d'implémentation

### Étape 1 : Override la sidebar Livewire

**Fichier** : `resources/views/vendor/filament/livewire/sidebar.blade.php`
**Source vendor** : `vendor/filament/filament/resources/views/livewire/sidebar.blade.php` (199 lignes)

Réécrire avec la structure HTML TailAdmin :
- `<aside>` fixed, largeur `w-[290px]` expanded / `w-[90px]` collapsed
- Fond `bg-white dark:bg-gray-900` avec `border-r`
- **Logo** : expanded = texte complet, collapsed = icône seule
- **Navigation** : boucle sur `filament()->getNavigation()` (identique au vendor), mais avec classes TailAdmin au lieu de `fi-sidebar-*`
  - Groupes avec label collapsible (réutilise `$store.sidebar.groupIsCollapsed(label)`)
  - Items avec icônes Heroicon, label, badge
  - Active state via TailAdmin classes (`bg-primary-50 text-primary-600`)
- **Tenant menu** : conserver `<x-filament-panels::tenant-menu />` si `filament()->hasTenancy()`
- **Footer** : user menu si position sidebar
- **Transitions** : `duration-300 ease-in-out` sur la largeur
- **Mobile** : sidebar en overlay (`fixed inset-0 z-50`)
- Alpine.js : réutiliser `$store.sidebar.isOpen` existant de Filament

### Étape 2 : Override la topbar Livewire

**Fichier** : `resources/views/vendor/filament/livewire/topbar.blade.php`
**Source vendor** : `vendor/filament/filament/resources/views/livewire/topbar.blade.php` (270 lignes)

Réécrire avec la structure HTML TailAdmin :
- `<header>` sticky top avec `border-b` et `shadow-sm`
- **Toggle sidebar** : bouton hamburger (mobile) + chevron (desktop)
- **Dark mode toggle** : bouton sun/moon avec `localStorage.setItem('theme', ...)` + Filament's `loadDarkMode()`
- **User dropdown** : avatar + nom + rôle + menu (profil, déconnexion)
  - Utiliser `filament()->auth()->user()` pour les infos
  - Réutiliser `<x-filament-panels::user-menu />` pour le dropdown
- **Notifications** : conserver le Livewire component si actif
- **Global search** : conserver si position topbar
- Conserver les render hooks : `TOPBAR_START`, `TOPBAR_END`, etc.

### Étape 3 : Override le layout index

**Fichier** : `resources/views/vendor/filament/components/layout/index.blade.php`
**Source vendor** : `vendor/filament/filament/resources/views/components/layout/index.blade.php` (119 lignes)

Adapter la structure wrapper :
- Conserver `<x-filament-panels::layout.base>` comme wrapper parent
- Structure flex : `<div class="flex min-h-screen">`
- Sidebar overlay backdrop : `<div x-show="$store.sidebar.isOpen" @click="$store.sidebar.close()" class="fixed inset-0 bg-black/50 z-40 lg:hidden">`
- `@livewire(filament()->getSidebarLivewireComponent())` pour la sidebar
- `@livewire(filament()->getTopbarLivewireComponent())` pour la topbar
- Main content : `<div class="flex-1 transition-all duration-300">` avec margin-left dynamique selon sidebar state
- Conserver `{{ $slot }}` pour le contenu Filament
- Conserver tous les render hooks (`LAYOUT_START/END`, `CONTENT_BEFORE/START/END/AFTER`, `FOOTER`)
- Conserver `<x-filament-actions::modals />` (modales)

### Étape 4 : Mettre à jour le CSS thème

**Fichier** : `resources/css/filament/app/theme.css`

Ajouter/modifier :
- Classes utilitaires TailAdmin (`.no-scrollbar`, `.menu-item`, `.menu-item-active`)
- Override des variables CSS Filament (`--sidebar-width: 290px`, `--collapsed-sidebar-width: 90px`)
- Transitions sidebar (`transition-all duration-300 ease-in-out`)
- Styles dark mode pour sidebar et topbar
- Override des fi-* classes existantes

### Étape 5 : Configurer le Panel Provider

**Fichier** : `app/Providers/Filament/AppPanelProvider.php`

Ajouter :
```php
->sidebarCollapsibleOnDesktop()
->darkMode()
->sidebarWidth('290px')
->collapsedSidebarWidth('90px')
```

### Étape 6 : Build et test

```bash
npm run build
php artisan filament:assets
```

---

## Architecture du layout

```
<html> (layout/base.blade.php - non modifié, vendor)
  <body class="fi-body">
    ├── <div class="flex min-h-screen">           ← layout/index.blade.php (override)
    │   ├── <div class="backdrop lg:hidden">       ← overlay mobile
    │   ├── <aside class="sidebar">                ← livewire/sidebar.blade.php (override)
    │   │   ├── Logo (expanded/collapsed)
    │   │   ├── Tenant menu
    │   │   ├── Navigation (filament()->getNavigation())
    │   │   │   ├── Group "Gestion des actifs"
    │   │   │   │   ├── Item "Actifs" (icon + label + badge)
    │   │   │   │   ├── Item "Assignations"
    │   │   │   │   └── ...
    │   │   │   └── Group "Configuration"
    │   │   └── Footer (user menu)
    │   └── <div class="flex-1 flex flex-col">
    │       ├── <header class="topbar">            ← livewire/topbar.blade.php (override)
    │       │   ├── Sidebar toggle
    │       │   ├── Dark mode toggle
    │       │   ├── Notifications
    │       │   └── User dropdown
    │       └── <main>                             ← Contenu Filament
    │           ├── Breadcrumbs
    │           ├── Page header + actions
    │           └── {{ $slot }} (forms, tables, relation managers)
    └── @livewire(Notifications)
        <x-filament-actions::modals />
```

---

## Vérification

1. Sidebar TailAdmin visible avec navigation Filament correcte
2. Collapse/expand sur desktop (290px ↔ 90px) avec transition fluide
3. Labels masqués quand collapsed, tooltip au hover
4. Mobile : sidebar overlay avec backdrop, fermeture au clic
5. Dark mode toggle fonctionnel (persiste dans localStorage)
6. User dropdown avec déconnexion
7. Tenant menu visible et fonctionnel (switch d'organisation)
8. Tous les formulaires/tables Filament fonctionnent dans le content area
9. Relation managers affichés correctement
10. Modales Filament (assign, create tag inline) fonctionnelles
11. `npm run build` sans erreur
