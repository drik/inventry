# Plan d'implémentation : Système d'abonnement et de facturation pour Inventry

## Contexte

La plateforme **Inventry** (Laravel 12 / Filament 3) gère l'inventaire physique d'organisations (tenants). Actuellement, toutes les organisations ont accès à toutes les fonctionnalités sans restriction. L'objectif est de mettre en place un système de souscription avec **4 niveaux de forfaits** qui limitent l'accès aux fonctionnalités selon le plan souscrit, avec intégration de **Paddle** comme système de paiement en ligne.

**Pourquoi Paddle** : Paddle agit comme **Merchant of Record** — il gère automatiquement la TVA/GST dans 200+ pays, les litiges, les chargebacks et la conformité fiscale. Inventry cible une vente mondiale (Europe, Afrique de l'Ouest, etc.), ce qui rend la gestion fiscale automatique essentielle. Frais : 5% + 0,50$ par transaction, tout inclus.

**Architecture existante clé** :
- Multi-tenancy via `Organization` (Filament `HasTenants`)
- Tous les modèles utilisent `HasUlids`
- Le champ `plan_id` (foreignUlid nullable) **existe déjà** dans la table `organizations`
- Trait `BelongsToOrganization` + `OrganizationScope` pour l'isolation des données
- Deux panneaux : Admin (`/admin`, SuperAdmin) et App (`/app`, tous)
- `UserRole` enum : SuperAdmin > Admin > Manager > Technician > User

---

## Forfaits et limites

| Fonctionnalité | Freemium (0€) | Basic (5€/mois) | Pro (35€/mois) | Premium (250€/mois) |
|---|---|---|---|---|
| Organisations | 1 | 1 | 3 | Illimité |
| Utilisateurs | 3 | 10 | 50 | Illimité |
| Actifs | 25 | 200 | 2 000 | Illimité |
| Sites (locations) | 1 | 5 | 20 | Illimité |
| Sessions d'inventaire actives | 1 | 3 | 10 | Illimité |
| Tâches par session | 2 | 5 | 20 | Illimité |
| Requêtes IA / jour | 0 | 5 | 50 | Illimité |
| Requêtes IA / mois | 0 | 50 | 500 | Illimité |
| Accès API mobile | Non | Oui | Oui | Oui |
| Export de données | Non | Oui | Oui | Oui |
| Analyses avancées | Non | Non | Oui | Oui |
| Intégrations sur mesure | Non | Non | Non | Oui |
| Support prioritaire | Non | Non | Non | Oui |

**Tarifs annuels** : 2 mois offerts (Basic: 50€/an, Pro: 350€/an, Premium: 2 500€/an)

**Période d'essai** : 14 jours du plan Pro pour toute nouvelle organisation (sans carte bancaire requise via generic trial).

---

## Phase 1 : Fondations — Schema de base de données et modèles

### 1.1 Installation Paddle

```bash
composer require laravel/cashier-paddle
php artisan vendor:publish --tag="cashier-migrations"
php artisan migrate
```

Cashier Paddle crée automatiquement les tables `customers` et `subscriptions` avec les colonnes nécessaires pour Paddle (paddle_id, etc.).

### 1.2 Configuration `.env`

```env
PADDLE_CLIENT_SIDE_TOKEN=pdl_...
PADDLE_API_KEY=your-paddle-api-key
PADDLE_RETAIN_KEY=your-paddle-retain-key
PADDLE_WEBHOOK_SECRET=pdl_ntfset_...
PADDLE_SANDBOX=true
```

### 1.3 Migration : Table `plans`

**Fichier** : `database/migrations/2026_02_22_000001_create_plans_table.php`

```php
Schema::create('plans', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('name');                              // "Freemium", "Basic", "Pro", "Premium"
    $table->string('slug')->unique();                    // "freemium", "basic", "pro", "premium"
    $table->text('description')->nullable();
    $table->unsignedInteger('price_monthly')->default(0);  // en centimes (500 = 5€)
    $table->unsignedInteger('price_yearly')->default(0);   // en centimes
    $table->json('limits');                              // limites de fonctionnalités (JSON)
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort_order')->default(0);
    $table->string('paddle_monthly_price_id')->nullable();  // ID du prix Paddle mensuel (pri_...)
    $table->string('paddle_yearly_price_id')->nullable();   // ID du prix Paddle annuel (pri_...)
    $table->timestamps();
    $table->softDeletes();
});
```

**Structure JSON du champ `limits`** :

```json
{
    "max_organizations": 1,
    "max_users": 5,
    "max_assets": 50,
    "max_locations": 2,
    "max_active_inventory_sessions": 1,
    "max_tasks_per_session": 3,
    "max_ai_requests_daily": 0,
    "max_ai_requests_monthly": 0,
    "has_api_access": false,
    "has_custom_integrations": false,
    "has_advanced_analytics": false,
    "has_priority_support": false,
    "has_export": false
}
```

Convention : `-1` = illimité, `0` = désactivé.

### 1.4 Migration : Table `ai_usage_logs`

**Fichier** : `database/migrations/2026_02_22_000002_create_ai_usage_logs_table.php`

```php
Schema::create('ai_usage_logs', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
    $table->string('feature');
    $table->unsignedInteger('tokens_used')->default(0);
    $table->timestamps();

    $table->index(['organization_id', 'created_at']);
});
```

### 1.5 Migration : Modification `organizations`

**Fichier** : `database/migrations/2026_02_22_000003_add_subscription_fields_to_organizations_table.php`

```php
Schema::table('organizations', function (Blueprint $table) {
    $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
});
```

Note : Le champ `plan_id` existe déjà. On ajoute seulement la contrainte FK. Les colonnes Paddle (`paddle_id`, etc.) sont gérées par la migration de Cashier Paddle sur la table `customers`.

### 1.6 Modèle `Plan`

**Fichier** : `app/Models/Plan.php`

- Trait `HasFactory`, `HasUlids`, `SoftDeletes`
- Méthodes : `getLimit(string $key)`, `hasFeature(string $key)`, `isFreemium()`
- Relations : `organizations()`, `subscriptions()`
- Scope : `scopeActive()`
- Accessors : `formatted_monthly_price`, `formatted_yearly_price`

### 1.7 Modèle `AiUsageLog`

**Fichier** : `app/Models/AiUsageLog.php`

- Trait `HasFactory`, `HasUlids`
- Relations : `organization()`, `user()`

### 1.8 Modification du modèle `Organization`

**Fichier à modifier** : `app/Models/Organization.php`

- Ajouter le trait `Billable` de `Laravel\Paddle\Billable`
- Ajouter les relations : `plan()`, `aiUsageLogs()`
- Note : les relations `subscriptions()` et `customer()` sont fournies automatiquement par le trait Billable de Paddle

---

## Phase 2 : Enums

### 2.1 `SubscriptionStatus`

**Fichier** : `app/Enums/SubscriptionStatus.php`

```php
enum SubscriptionStatus: string implements HasLabel, HasColor, HasIcon
{
    case Active = 'active';          // Actif (vert)
    case Trialing = 'trialing';      // Essai (bleu)
    case PastDue = 'past_due';       // En retard (orange)
    case Cancelled = 'cancelled';    // Annulé (rouge)
    case Paused = 'paused';          // En pause (gris)
}
```

Note : Paddle utilise ses propres statuts via Cashier. Cet enum est pour notre usage interne d'affichage.

### 2.2 `BillingCycle`

**Fichier** : `app/Enums/BillingCycle.php`

```php
enum BillingCycle: string implements HasLabel
{
    case Monthly = 'monthly';   // Mensuel
    case Yearly = 'yearly';     // Annuel
}
```

### 2.3 `PlanFeature`

**Fichier** : `app/Enums/PlanFeature.php`

```php
enum PlanFeature: string implements HasLabel
{
    // Limites numériques
    case MaxOrganizations = 'max_organizations';
    case MaxUsers = 'max_users';
    case MaxAssets = 'max_assets';
    case MaxLocations = 'max_locations';
    case MaxActiveInventorySessions = 'max_active_inventory_sessions';
    case MaxTasksPerSession = 'max_tasks_per_session';
    case MaxAiRequestsDaily = 'max_ai_requests_daily';
    case MaxAiRequestsMonthly = 'max_ai_requests_monthly';

    // Features booléennes
    case HasApiAccess = 'has_api_access';
    case HasCustomIntegrations = 'has_custom_integrations';
    case HasAdvancedAnalytics = 'has_advanced_analytics';
    case HasPrioritySupport = 'has_priority_support';
    case HasExport = 'has_export';
}
```

Méthodes : `isNumericLimit()`, `isBooleanFeature()`, `getCountableModel()`.

---

## Phase 3 : Service de vérification des limites

### 3.1 `PlanLimitService`

**Fichier** : `app/Services/PlanLimitService.php`

Service central (singleton dans `AppServiceProvider`).

**Méthodes publiques** :

```php
class PlanLimitService
{
    public function getEffectivePlan(Organization $org): Plan;
    public function getLimit(Organization $org, PlanFeature $feature): int;
    public function getCurrentUsage(Organization $org, PlanFeature $feature, ?string $parentId = null): int;
    public function canCreate(Organization $org, PlanFeature $feature, ?string $parentId = null): bool;
    public function hasFeature(Organization $org, PlanFeature $feature): bool;
    public function getUsageStats(Organization $org, PlanFeature $feature, ?string $parentId = null): array;
    public function canCreateOrganization(User $user): bool;
    public function getLimitReachedMessage(PlanFeature $feature, Organization $org): string;
}
```

---

## Phase 4 : Middleware et trait Filament pour les limites

### 4.1 Middleware API `CheckPlanLimit`

**Fichier** : `app/Http/Middleware/CheckPlanLimit.php`

Retourne 403 si la limite est atteinte.

### 4.2 Trait Filament `ChecksPlanLimits`

**Fichier** : `app/Filament/Concerns/ChecksPlanLimits.php`

Vérifie la limite au `mount()` des pages `CreateRecord`.

---

## Phase 5 : Intégration Paddle avec Laravel Cashier Paddle

### 5.1 Configuration du Billable sur Organization

Le trait `Billable` de Cashier Paddle est ajouté au modèle `Organization` (pas `User`). C'est l'organisation qui souscrit et paie.

```php
use Laravel\Paddle\Billable;

class Organization extends Model implements HasCurrentTenantLabel
{
    use Billable, HasFactory, HasUlids, SoftDeletes;
}
```

### 5.2 Ajouter `@paddleJS` au layout Filament

**Fichier à modifier** : `app/Providers/Filament/AppPanelProvider.php`

Ajouter un render hook pour charger le JS de Paddle :

```php
->renderHook(
    PanelsRenderHook::HEAD_END,
    fn (): string => Blade::render('@paddleJS'),
)
```

### 5.3 Créer les produits et prix dans Paddle Dashboard

Avant de coder, créer dans le **Paddle Sandbox Dashboard** :
- **Produit "Inventry Basic"** → prix mensuel `pri_basic_monthly` (5€) + annuel `pri_basic_yearly` (50€)
- **Produit "Inventry Pro"** → prix mensuel `pri_pro_monthly` (35€) + annuel `pri_pro_yearly` (350€)
- **Produit "Inventry Premium"** → prix mensuel `pri_premium_monthly` (250€) + annuel `pri_premium_yearly` (2500€)
- Configurer la période d'essai de 14 jours sur les prix Pro

### 5.4 Service `PaddleSubscriptionService`

**Fichier** : `app/Services/PaddleSubscriptionService.php`

```php
class PaddleSubscriptionService
{
    public function __construct(
        protected PlanLimitService $planLimitService,
    ) {}

    public function createCheckout(Organization $organization, Plan $plan, BillingCycle $cycle, string $returnUrl): \Laravel\Paddle\Checkout;
    public function changePlan(Organization $organization, Plan $newPlan, BillingCycle $cycle): void;
    public function cancel(Organization $organization): void;
    public function cancelNow(Organization $organization): void;
    public function pause(Organization $organization): void;
    public function resume(Organization $organization): void;
    public function startGenericTrial(Organization $organization, Plan $plan, int $trialDays = 14): void;
    public function downgradeToFree(Organization $organization): void;
}
```

### 5.5 Événements Paddle (remplace les webhooks manuels)

Cashier Paddle dispatche des événements Laravel automatiquement. On écoute ces événements dans un Listener.

**Fichier** : `app/Listeners/HandlePaddleSubscription.php`

```php
class HandlePaddleSubscription
{
    public function handleCreated(SubscriptionCreated $event): void;
    public function handleCanceled(SubscriptionCanceled $event): void;
}
```

**Enregistrement dans `AppServiceProvider::boot()`** :

```php
Event::listen(SubscriptionCreated::class, [HandlePaddleSubscription::class, 'handleCreated']);
Event::listen(SubscriptionCanceled::class, [HandlePaddleSubscription::class, 'handleCanceled']);
```

### 5.6 Configuration des Webhooks Paddle

Dans le **Paddle Dashboard** → Settings → Notifications, configurer l'URL :
```
https://votre-domaine.com/paddle/webhook
```

**Exclure du CSRF** dans `bootstrap/app.php` :
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: [
        'paddle/*',
    ]);
})
```

---

## Phase 6 : Interface Filament — Panel App (utilisateurs)

### 6.1 Page d'abonnement

**Fichier** : `app/Filament/App/Pages/Subscription.php`
**Vue** : `resources/views/filament/app/pages/subscription.blade.php`

Accessible uniquement au owner de l'organisation. Contenu :

1. **Plan actuel** : Nom, statut (badge), date de renouvellement, prix
2. **Utilisation** : Barres de progression pour chaque limite (ex: `15/50 actifs`)
3. **Plans disponibles** : Grille comparative avec les 4 forfaits
4. **Boutons d'action** :
   - Pour chaque plan payant : `<x-paddle-button :checkout="$checkout">Souscrire</x-paddle-button>` (overlay Paddle)
   - Changer de plan : bouton swap
   - Annuler / Mettre en pause / Reprendre
5. **Factures** : via `$organization->transactions` (Cashier Paddle)

### 6.2 Widget d'utilisation du plan

**Fichier** : `app/Filament/Widgets/PlanUsageWidget.php`
**Vue** : `resources/views/filament/widgets/plan-usage.blade.php`

Widget affiché en premier sur le dashboard App. Barres de progression (actifs, utilisateurs, locations, sessions).

---

## Phase 7 : Interface Filament — Panel Admin (SuperAdmin)

### 7.1 `PlanResource`

**Fichier** : `app/Filament/Resources/PlanResource.php`

CRUD des plans :
- **Formulaire** : name, slug, description, prix, limits (JSON/KeyValue), Paddle price IDs, is_active
- **Table** : name, prix formaté, nombre d'orgs, is_active

### 7.2 `SubscriptionResource`

**Fichier** : `app/Filament/Resources/SubscriptionResource.php`

Consultation des abonnements :
- **Table** : Organisation, plan, statut (badge), cycle, période
- **Filtres** : Par statut, par plan

### 7.3 Widget `SubscriptionStatsOverview`

**Fichier** : `app/Filament/Widgets/Admin/SubscriptionStatsOverview.php`

Stats : MRR, abonnements actifs, essais en cours, répartition par plan.

---

## Phase 8 : Endpoints API pour l'application mobile

### 8.1 Nouvelles routes

```
GET  /api/subscription/current   → SubscriptionController@current   [auth:sanctum]
GET  /api/subscription/plans     → SubscriptionController@plans     [auth:sanctum]
GET  /api/subscription/usage     → SubscriptionController@usage     [auth:sanctum]
```

### 8.2 Contrôleur `SubscriptionController`

**Fichier** : `app/Http/Controllers/Api/SubscriptionController.php`

- `current()` → plan actuel + statut abonnement + trial info
- `plans()` → liste des plans avec limites et prix (via `Cashier::previewPrices()` pour les prix localisés)
- `usage()` → stats d'utilisation pour chaque feature

---

## Phase 9 : Seeder des plans par défaut

### `PlanSeeder`

**Fichier** : `database/seeders/PlanSeeder.php`

Crée les 4 plans avec `updateOrCreate` :

| Plan | Prix mensuel | Prix annuel | Paddle Monthly ID | Paddle Yearly ID |
|------|-------------|-------------|-------------------|------------------|
| Freemium | 0€ | 0€ | null | null |
| Basic | 5€ | 50€ | `pri_basic_monthly` | `pri_basic_yearly` |
| Pro | 35€ | 350€ | `pri_pro_monthly` | `pri_pro_yearly` |
| Premium | 250€ | 2500€ | `pri_premium_monthly` | `pri_premium_yearly` |

Les `pri_*` sont des placeholders — à remplacer par les vrais IDs du Paddle Dashboard.

---

## Phase 10 : Notifications et automatisation

### Notifications à créer

| Fichier | Déclencheur |
|---------|------------|
| `app/Notifications/PaymentFailed.php` | Événement Paddle `TransactionUpdated` (status=past_due) |
| `app/Notifications/TrialEnding.php` | Commande schedulée (3 jours avant fin) |
| `app/Notifications/SubscriptionChanged.php` | Événement Paddle `SubscriptionUpdated` |

### Commande schedulée

**Fichier** : `app/Console/Commands/CheckTrialExpiry.php`

Exécutée quotidiennement :
- Envoie `TrialEnding` 3 jours avant la fin de l'essai
- Rétrograde au Freemium les essais expirés (generic trial)

---

## Résumé des fichiers

### Fichiers à créer (26)

| # | Fichier | Description |
|---|---------|-------------|
| 1 | `database/migrations/2026_02_22_000001_create_plans_table.php` | Table des plans |
| 2 | `database/migrations/2026_02_22_000002_create_ai_usage_logs_table.php` | Table suivi IA |
| 3 | `database/migrations/2026_02_22_000003_add_subscription_fields_to_organizations_table.php` | FK plan_id |
| 4 | `app/Enums/SubscriptionStatus.php` | Enum statut abonnement |
| 5 | `app/Enums/BillingCycle.php` | Enum cycle facturation |
| 6 | `app/Enums/PlanFeature.php` | Enum clés features |
| 7 | `app/Models/Plan.php` | Modèle Plan |
| 8 | `app/Models/AiUsageLog.php` | Modèle suivi IA |
| 9 | `app/Services/PlanLimitService.php` | Service vérification limites |
| 10 | `app/Services/PaddleSubscriptionService.php` | Service gestion Paddle |
| 11 | `app/Http/Middleware/CheckPlanLimit.php` | Middleware limites API |
| 12 | `app/Filament/Concerns/ChecksPlanLimits.php` | Trait Filament limites |
| 13 | `app/Listeners/HandlePaddleSubscription.php` | Listener événements Paddle |
| 14 | `app/Http/Controllers/Api/SubscriptionController.php` | Contrôleur API |
| 15 | `app/Filament/App/Pages/Subscription.php` | Page abonnement App |
| 16 | `resources/views/filament/app/pages/subscription.blade.php` | Vue page abonnement |
| 17 | `app/Filament/Widgets/PlanUsageWidget.php` | Widget utilisation |
| 18 | `resources/views/filament/widgets/plan-usage.blade.php` | Vue widget |
| 19 | `app/Filament/Resources/PlanResource.php` | Resource plans Admin |
| 20 | `app/Filament/Resources/SubscriptionResource.php` | Resource abonnements Admin |
| 21 | `app/Filament/Widgets/Admin/SubscriptionStatsOverview.php` | Widget stats Admin |
| 22 | `database/seeders/PlanSeeder.php` | Seeder plans |
| 23 | `app/Notifications/PaymentFailed.php` | Notification paiement |
| 24 | `app/Notifications/TrialEnding.php` | Notification essai |
| 25 | `app/Notifications/SubscriptionChanged.php` | Notification changement |
| 26 | `app/Console/Commands/CheckTrialExpiry.php` | Commande schedulée |

### Fichiers à modifier (13)

| # | Fichier | Modification |
|---|---------|-------------|
| 1 | `app/Models/Organization.php` | Trait `Laravel\Paddle\Billable`, relation `plan()`, `aiUsageLogs()` |
| 2 | `app/Providers/AppServiceProvider.php` | Singleton PlanLimitService + Event listeners Paddle |
| 3 | `AssetResource/Pages/CreateAsset.php` | Trait ChecksPlanLimits |
| 4 | `UserResource/Pages/CreateUser.php` | Trait ChecksPlanLimits |
| 5 | `LocationResource/Pages/CreateLocation.php` | Trait ChecksPlanLimits |
| 6 | `InventorySessionResource/Pages/CreateInventorySession.php` | Trait ChecksPlanLimits |
| 7 | `InventorySessionResource/RelationManagers/*` | Vérification limites tâches |
| 8 | `app/Filament/App/Pages/RegisterOrganization.php` | Vérification limite orgs + trial |
| 9 | `database/seeders/DatabaseSeeder.php` | Ajouter PlanSeeder |
| 10 | `routes/api.php` | Routes subscription + middleware |
| 11 | `bootstrap/app.php` | Alias middleware + CSRF exception `paddle/*` |
| 12 | `composer.json` | Ajouter `laravel/cashier-paddle` |
| 13 | `app/Providers/Filament/AppPanelProvider.php` | `@paddleJS` + Widget PlanUsage |

---

## Ordre d'implémentation recommandé

### Étape 1 : Fondations (Priorité critique)
1. `composer require laravel/cashier-paddle` + publish migrations
2. Créer les 3 migrations custom (plans, ai_usage_logs, organizations FK)
3. Créer les 3 enums (SubscriptionStatus, BillingCycle, PlanFeature)
4. Créer les 2 modèles (Plan, AiUsageLog)
5. Modifier Organization (Billable Paddle, relations)
6. Créer et exécuter PlanSeeder

### Étape 2 : Service de limites (Priorité haute)
7. Créer PlanLimitService
8. Enregistrer dans AppServiceProvider
9. Créer le trait ChecksPlanLimits
10. Appliquer aux pages CreateRecord

### Étape 3 : Interface App (Priorité haute)
11. Créer la page Subscription avec overlay Paddle
12. Créer la vue Blade (grille plans + barres utilisation)
13. Créer le widget PlanUsageWidget
14. Intégrer `@paddleJS` et widget au AppPanelProvider

### Étape 4 : Paddle (Priorité haute)
15. Configurer .env avec les clés Paddle Sandbox
16. Créer PaddleSubscriptionService
17. Créer HandlePaddleSubscription listener
18. Configurer CSRF exception dans bootstrap/app.php
19. Créer produits/prix dans Paddle Dashboard
20. Mettre à jour paddle_*_price_id dans le seeder

### Étape 5 : Panel Admin (Priorité moyenne)
21. Créer PlanResource
22. Créer SubscriptionResource
23. Créer SubscriptionStatsOverview

### Étape 6 : API Mobile (Priorité moyenne)
24. Créer SubscriptionController API
25. Ajouter routes API
26. Créer middleware CheckPlanLimit
27. Appliquer aux routes API de création

### Étape 7 : Notifications (Priorité basse)
28. Créer les 3 notifications
29. Créer la commande CheckTrialExpiry
30. Configurer le scheduler

### Étape 8 : Finalisation (Priorité basse)
31. Protéger RegisterOrganization + generic trial
32. Tests

---

## Considérations importantes

1. **Dégradation gracieuse** : Le downgrade ne supprime JAMAIS les données existantes. Seule la création de nouvelles ressources est bloquée.
2. **Convention -1 = illimité** : Dans le JSON `limits`, -1 = sans limite.
3. **Cache** : Mettre en cache le plan de l'organisation (`Cache::remember("org:{$id}:plan", 3600, ...)`).
4. **Période d'essai** : Generic trial de 14 jours (sans carte bancaire) via `createAsCustomer(['trial_ends_at' => ...])`.
5. **Paddle Sandbox** : Commencer en mode sandbox. Les prix Paddle doivent être créés dans le Sandbox Dashboard.
6. **Webhooks** : Cashier Paddle gère automatiquement la vérification des signatures et le routage.
7. **ULIDs** : Tous les nouveaux modèles utilisent `HasUlids`.
8. **Paddle = Merchant of Record** : Les factures sont émises par Paddle au nom de Paddle. Inventry n'a pas besoin de gérer la TVA/conformité fiscale.
9. **Prix localisés** : Utiliser `Cashier::previewPrices()` pour afficher les prix dans la devise locale du client.
