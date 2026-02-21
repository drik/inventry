# Plan d'implémentation : Système d'abonnement et de facturation pour Inventry

## Contexte

La plateforme **Inventry** (Laravel 12 / Filament 3) gère l'inventaire physique d'organisations (tenants). Actuellement, toutes les organisations ont accès à toutes les fonctionnalités sans restriction. L'objectif est de mettre en place un système de souscription avec **4 niveaux de forfaits** qui limitent l'accès aux fonctionnalités selon le plan souscrit, avec intégration d'un système de paiement en ligne (Stripe).

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

**Période d'essai** : 14 jours du plan Pro pour toute nouvelle organisation.

---

## Phase 1 : Fondations — Schema de base de données et modèles

### 1.1 Migration : Table `plans`

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
    $table->string('stripe_monthly_price_id')->nullable();
    $table->string('stripe_yearly_price_id')->nullable();
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

### 1.2 Migration : Table `subscriptions`

**Fichier** : `database/migrations/2026_02_22_000002_create_subscriptions_table.php`

```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignUlid('plan_id')->constrained()->cascadeOnDelete();
    $table->string('status');                            // SubscriptionStatus enum
    $table->string('billing_cycle');                     // 'monthly' ou 'yearly'
    $table->string('stripe_subscription_id')->nullable()->unique();
    $table->string('stripe_customer_id')->nullable();
    $table->timestamp('trial_starts_at')->nullable();
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('current_period_start')->nullable();
    $table->timestamp('current_period_end')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['organization_id', 'status']);
});
```

### 1.3 Migration : Table `ai_usage_logs`

**Fichier** : `database/migrations/2026_02_22_000003_create_ai_usage_logs_table.php`

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

### 1.4 Migration : Modification `organizations`

**Fichier** : `database/migrations/2026_02_22_000004_add_subscription_fields_to_organizations_table.php`

```php
Schema::table('organizations', function (Blueprint $table) {
    $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
    $table->string('stripe_customer_id')->nullable()->after('plan_id');
});
```

### 1.5 Modèle `Plan`

**Fichier** : `app/Models/Plan.php`

- Trait `HasFactory`, `HasUlids`, `SoftDeletes`
- Méthodes : `getLimit(string $key)`, `hasFeature(string $key)`, `isFreemium()`
- Relations : `organizations()`, `subscriptions()`
- Scope : `scopeActive()`
- Accessors : `formatted_monthly_price`, `formatted_yearly_price`

### 1.6 Modèle `Subscription`

**Fichier** : `app/Models/Subscription.php`

- Trait `HasFactory`, `HasUlids`, `SoftDeletes`
- Casts : `status` → `SubscriptionStatus`, `billing_cycle` → `BillingCycle`, dates
- Relations : `organization()`, `plan()`
- Méthodes : `isActive()`, `isOnTrial()`, `isOnGracePeriod()`
- Scope : `scopeActive()`

### 1.7 Modèle `AiUsageLog`

**Fichier** : `app/Models/AiUsageLog.php`

- Trait `HasFactory`, `HasUlids`
- Relations : `organization()`, `user()`

### 1.8 Modification du modèle `Organization`

**Fichier à modifier** : `app/Models/Organization.php`

- Ajouter le trait `Billable` de Laravel Cashier
- Ajouter `stripe_customer_id` au `$fillable`
- Ajouter les relations : `plan()`, `subscriptions()`, `activeSubscription()`, `aiUsageLogs()`

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
    case Expired = 'expired';        // Expiré (gris)
    case Incomplete = 'incomplete';  // Incomplet (orange)
    case Paused = 'paused';          // En pause (gris)
}
```

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

Centralise les clés de fonctionnalités du JSON `limits` :

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

Service central (singleton dans `AppServiceProvider`) qui gère toute la logique de vérification.

**Méthodes publiques** :

```php
class PlanLimitService
{
    // Récupère le plan effectif (ou Freemium par défaut)
    public function getEffectivePlan(Organization $org): Plan;

    // Récupère la limite pour une feature
    public function getLimit(Organization $org, PlanFeature $feature): int;

    // Obtient l'utilisation actuelle
    public function getCurrentUsage(Organization $org, PlanFeature $feature, ?string $parentId = null): int;

    // Vérifie si on peut créer une ressource (usage < limite)
    public function canCreate(Organization $org, PlanFeature $feature, ?string $parentId = null): bool;

    // Vérifie une feature booléenne
    public function hasFeature(Organization $org, PlanFeature $feature): bool;

    // Stats d'utilisation pour l'affichage (current, limit, percentage, remaining, is_at_limit)
    public function getUsageStats(Organization $org, PlanFeature $feature, ?string $parentId = null): array;

    // Vérifie la limite d'organisations pour un user
    public function canCreateOrganization(User $user): bool;

    // Message d'erreur formaté
    public function getLimitReachedMessage(PlanFeature $feature, Organization $org): string;
}
```

**Logique de `getCurrentUsage()`** :
- `MaxUsers` → `$org->users()->count()`
- `MaxAssets` → `$org->assets()->count()`
- `MaxLocations` → `$org->locations()->count()`
- `MaxActiveInventorySessions` → sessions avec status Draft ou InProgress
- `MaxTasksPerSession` → `InventoryTask::where('session_id', $parentId)->count()`
- `MaxAiRequestsDaily` → logs IA du jour
- `MaxAiRequestsMonthly` → logs IA du mois

---

## Phase 4 : Middleware et trait Filament pour les limites

### 4.1 Middleware API `CheckPlanLimit`

**Fichier** : `app/Http/Middleware/CheckPlanLimit.php`

Pour les routes API (Sanctum). Retourne 403 avec message et `upgrade_required: true` si la limite est atteinte.

```php
// Utilisation dans routes/api.php :
Route::post('/assets', [AssetController::class, 'store'])
    ->middleware('plan.limit:max_assets');
```

Enregistrer l'alias dans `bootstrap/app.php`.

### 4.2 Trait Filament `ChecksPlanLimits`

**Fichier** : `app/Filament/Concerns/ChecksPlanLimits.php`

Trait pour les pages `CreateRecord` de Filament. Vérifie la limite au `mount()` et redirige avec notification si la limite est atteinte, avec un bouton "Mettre à niveau".

**Pages à modifier** avec ce trait :

| Page | Feature |
|------|---------|
| `CreateAsset` | `PlanFeature::MaxAssets` |
| `CreateUser` | `PlanFeature::MaxUsers` |
| `CreateLocation` | `PlanFeature::MaxLocations` |
| `CreateInventorySession` | `PlanFeature::MaxActiveInventorySessions` |
| `RegisterOrganization` | `PlanFeature::MaxOrganizations` (via `canCreateOrganization()`) |
| RelationManager tâches | `PlanFeature::MaxTasksPerSession` |

---

## Phase 5 : Intégration Stripe avec Laravel Cashier

### 5.1 Installation

```bash
composer require laravel/cashier
```

### 5.2 Configuration `.env`

```
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
CASHIER_CURRENCY=eur
CASHIER_CURRENCY_LOCALE=fr_FR
```

### 5.3 Billable sur Organization

Le trait `Billable` de Cashier est ajouté au modèle `Organization` (pas `User`), car c'est l'organisation qui est le "client" Stripe.

### 5.4 Service `StripeSubscriptionService`

**Fichier** : `app/Services/StripeSubscriptionService.php`

```php
class StripeSubscriptionService
{
    // Crée un checkout Stripe (redirige vers Stripe pour le paiement)
    public function createCheckoutSession(Organization $org, Plan $plan, BillingCycle $cycle, ...): Checkout;

    // Change de plan (upgrade/downgrade via Cashier swap)
    public function changePlan(Organization $org, Plan $newPlan, BillingCycle $cycle): Subscription;

    // Annule l'abonnement (fin à la période actuelle)
    public function cancel(Organization $org): Subscription;

    // Réactive un abonnement annulé
    public function resume(Organization $org): Subscription;

    // Démarre une période d'essai (14 jours Pro)
    public function startTrial(Organization $org, Plan $plan, int $trialDays = 14): Subscription;

    // Rétrograde au plan Freemium
    public function downgradeToFree(Organization $org): void;
}
```

### 5.5 Webhooks Stripe

**Fichier** : `app/Http/Controllers/Webhooks/StripeWebhookController.php`

Étend `Laravel\Cashier\Http\Controllers\WebhookController` et gère :
- `customer.subscription.created` → Crée le `Subscription` local + met à jour `organization.plan_id`
- `customer.subscription.updated` → Met à jour le statut et les dates
- `customer.subscription.deleted` → Passe le statut à Expired, rétrograde au Freemium
- `invoice.payment_failed` → Passe le statut à PastDue, notifie le owner

**Route** : `POST /stripe/webhook` dans `routes/web.php`

---

## Phase 6 : Interface Filament — Panel App (utilisateurs)

### 6.1 Page d'abonnement

**Fichier** : `app/Filament/App/Pages/Subscription.php`
**Vue** : `resources/views/filament/app/pages/subscription.blade.php`

Accessible uniquement au owner de l'organisation. Contenu :

1. **Plan actuel** : Nom, statut (badge), date de renouvellement, prix
2. **Utilisation** : Barres de progression pour chaque limite (ex: `15/50 actifs`)
3. **Plans disponibles** : Grille comparative avec les 4 forfaits et boutons upgrade/downgrade
4. **Factures** : Tableau des factures passées (via `$organization->invoices()`)
5. **Gestion** : Boutons annuler / réactiver / modifier le moyen de paiement

### 6.2 Widget d'utilisation du plan

**Fichier** : `app/Filament/Widgets/PlanUsageWidget.php`
**Vue** : `resources/views/filament/widgets/plan-usage.blade.php`

Widget affiché en premier sur le dashboard App. Montre un résumé compact de l'utilisation : nom du plan, barres de progression principales (actifs, utilisateurs, locations, sessions).

---

## Phase 7 : Interface Filament — Panel Admin (SuperAdmin)

### 7.1 `PlanResource`

**Fichier** : `app/Filament/Resources/PlanResource.php`

CRUD des plans pour le SuperAdmin :
- **Formulaire** : name, slug, description, prix, limits (JSON/KeyValue), Stripe price IDs, is_active
- **Table** : name, prix formaté, nombre d'orgs, is_active

### 7.2 `SubscriptionResource`

**Fichier** : `app/Filament/Resources/SubscriptionResource.php`

Consultation des abonnements :
- **Table** : Organisation, plan, statut (badge), cycle, période, créé le
- **Filtres** : Par statut, par plan
- **Actions** : Forcer un changement de plan, annuler

### 7.3 Widget `SubscriptionStatsOverview`

**Fichier** : `app/Filament/Widgets/Admin/SubscriptionStatsOverview.php`

Stats : MRR, abonnements actifs, essais en cours, taux de churn, répartition par plan.

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

- `current()` → plan actuel + statut abonnement
- `plans()` → liste des plans disponibles avec limites
- `usage()` → stats d'utilisation pour chaque feature

### 8.3 Middleware sur les routes existantes

Appliquer `plan.limit` aux routes de création de ressources dans `routes/api.php`.

---

## Phase 9 : Seeder des plans par défaut

### `PlanSeeder`

**Fichier** : `database/seeders/PlanSeeder.php`

Crée les 4 plans avec `updateOrCreate` (idempotent) :

| Plan | Prix mensuel | Prix annuel | Limites clés |
|------|-------------|-------------|-------------|
| Freemium | 0€ | 0€ | 3 users, 25 assets, 1 location, 0 IA |
| Basic | 5€ | 50€ | 10 users, 200 assets, 5 locations, 5 IA/jour |
| Pro | 35€ | 350€ | 50 users, 2000 assets, 20 locations, 50 IA/jour |
| Premium | 250€ | 2500€ | Tout illimité (-1) |

Modifier `DatabaseSeeder` pour appeler `PlanSeeder` en premier.

---

## Phase 10 : Notifications et automatisation

### Notifications à créer

| Fichier | Déclencheur |
|---------|------------|
| `app/Notifications/PaymentFailed.php` | Webhook `invoice.payment_failed` |
| `app/Notifications/TrialEnding.php` | Commande schedulée (3 jours avant fin) |
| `app/Notifications/SubscriptionChanged.php` | Changement de plan |

### Commande schedulée

**Fichier** : `app/Console/Commands/CheckTrialExpiry.php`

Exécutée quotidiennement :
- Envoie `TrialEnding` 3 jours avant la fin de l'essai
- Rétrograde au Freemium les essais expirés

---

## Résumé des fichiers

### Fichiers à créer (28)

| # | Fichier | Description |
|---|---------|-------------|
| 1 | `database/migrations/2026_02_22_000001_create_plans_table.php` | Table des plans |
| 2 | `database/migrations/2026_02_22_000002_create_subscriptions_table.php` | Table des abonnements |
| 3 | `database/migrations/2026_02_22_000003_create_ai_usage_logs_table.php` | Table suivi IA |
| 4 | `database/migrations/2026_02_22_000004_add_subscription_fields_to_organizations_table.php` | Colonnes organisations |
| 5 | `app/Enums/SubscriptionStatus.php` | Enum statut abonnement |
| 6 | `app/Enums/BillingCycle.php` | Enum cycle facturation |
| 7 | `app/Enums/PlanFeature.php` | Enum clés features |
| 8 | `app/Models/Plan.php` | Modèle Plan |
| 9 | `app/Models/Subscription.php` | Modèle Subscription |
| 10 | `app/Models/AiUsageLog.php` | Modèle suivi IA |
| 11 | `app/Services/PlanLimitService.php` | Service vérification limites |
| 12 | `app/Services/StripeSubscriptionService.php` | Service gestion Stripe |
| 13 | `app/Http/Middleware/CheckPlanLimit.php` | Middleware limites API |
| 14 | `app/Filament/Concerns/ChecksPlanLimits.php` | Trait Filament limites |
| 15 | `app/Http/Controllers/Webhooks/StripeWebhookController.php` | Webhooks Stripe |
| 16 | `app/Http/Controllers/Api/SubscriptionController.php` | Contrôleur API |
| 17 | `app/Filament/App/Pages/Subscription.php` | Page abonnement App |
| 18 | `resources/views/filament/app/pages/subscription.blade.php` | Vue page abonnement |
| 19 | `app/Filament/Widgets/PlanUsageWidget.php` | Widget utilisation |
| 20 | `resources/views/filament/widgets/plan-usage.blade.php` | Vue widget |
| 21 | `app/Filament/Resources/PlanResource.php` | Resource plans Admin |
| 22 | `app/Filament/Resources/SubscriptionResource.php` | Resource abonnements Admin |
| 23 | `app/Filament/Widgets/Admin/SubscriptionStatsOverview.php` | Widget stats Admin |
| 24 | `database/seeders/PlanSeeder.php` | Seeder plans |
| 25 | `app/Notifications/PaymentFailed.php` | Notification paiement |
| 26 | `app/Notifications/TrialEnding.php` | Notification essai |
| 27 | `app/Notifications/SubscriptionChanged.php` | Notification changement |
| 28 | `app/Console/Commands/CheckTrialExpiry.php` | Commande schedulée |

### Fichiers à modifier (15)

| # | Fichier | Modification |
|---|---------|-------------|
| 1 | `app/Models/Organization.php` | Trait Billable, relations plan/subscription, stripe_customer_id |
| 2 | `app/Providers/AppServiceProvider.php` | Singleton PlanLimitService |
| 3 | `AssetResource/Pages/CreateAsset.php` | Trait ChecksPlanLimits |
| 4 | `UserResource/Pages/CreateUser.php` | Trait ChecksPlanLimits |
| 5 | `LocationResource/Pages/CreateLocation.php` | Trait ChecksPlanLimits |
| 6 | `InventorySessionResource/Pages/CreateInventorySession.php` | Trait ChecksPlanLimits |
| 7 | `InventorySessionResource/RelationManagers/*` | Vérification limites tâches |
| 8 | `app/Filament/App/Pages/RegisterOrganization.php` | Vérification limite orgs |
| 9 | `database/seeders/DatabaseSeeder.php` | Ajouter PlanSeeder |
| 10 | `routes/api.php` | Routes subscription + middleware |
| 11 | `routes/web.php` | Route webhook Stripe |
| 12 | `bootstrap/app.php` | Alias middleware plan.limit |
| 13 | `composer.json` | Ajouter laravel/cashier |
| 14 | `app/Providers/Filament/AppPanelProvider.php` | Widget PlanUsage |
| 15 | `.env` | Clés Stripe |

---

## Ordre d'implémentation recommandé

### Étape 1 : Fondations (Priorité critique)
1. `composer require laravel/cashier`
2. Créer les 4 migrations et les exécuter
3. Créer les 3 enums (SubscriptionStatus, BillingCycle, PlanFeature)
4. Créer les 3 modèles (Plan, Subscription, AiUsageLog)
5. Modifier Organization (Billable, relations)
6. Créer et exécuter PlanSeeder

### Étape 2 : Service de limites (Priorité haute)
7. Créer PlanLimitService
8. Enregistrer dans AppServiceProvider
9. Créer le trait ChecksPlanLimits
10. Appliquer aux pages CreateRecord

### Étape 3 : Interface App (Priorité haute)
11. Créer la page Subscription
12. Créer la vue Blade
13. Créer le widget PlanUsageWidget
14. Intégrer au AppPanelProvider

### Étape 4 : Stripe (Priorité haute)
15. Configurer .env avec les clés Stripe
16. Créer StripeSubscriptionService
17. Créer StripeWebhookController
18. Configurer les routes webhook
19. Créer produits/prix dans Stripe Dashboard
20. Mettre à jour stripe_*_price_id dans le seeder

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
31. Protéger RegisterOrganization
32. Gestion facturation annuelle
33. Tests

---

## Considérations importantes

1. **Dégradation gracieuse** : Le downgrade ne supprime JAMAIS les données existantes. Seule la création de nouvelles ressources est bloquée.

2. **Convention -1 = illimité** : Dans le JSON `limits`, -1 = sans limite. `PlanLimitService::canCreate()` retourne toujours `true` pour -1.

3. **Cache** : Mettre en cache le plan de l'organisation pour éviter des requêtes répétées (`Cache::remember("org:{$id}:plan", 3600, ...)`).

4. **Période d'essai** : 14 jours Pro à la création. Rétrogradation automatique au Freemium après expiration.

5. **Stripe test** : Commencer en mode test. Les prix Stripe doivent être créés dans le Dashboard Stripe.

6. **Sécurité webhooks** : Vérification via signature `Stripe-Signature` (géré par Cashier).

7. **ULIDs** : Tous les nouveaux modèles utilisent `HasUlids` (cohérence avec le codebase).

---

## Vérification

### Backend
1. `composer require laravel/cashier` + `php artisan migrate`
2. `php artisan db:seed --class=PlanSeeder`
3. Vérifier que les 4 plans sont créés en base
4. Créer un asset dans une org Freemium ayant déjà 25 assets → vérifier le blocage
5. Tester `GET /api/subscription/current` avec Postman
6. Tester `GET /api/subscription/usage` → vérifier les stats
7. Configurer Stripe test → créer un checkout → vérifier le webhook
8. Vérifier que le plan_id de l'organisation se met à jour après paiement
9. Annuler l'abonnement → vérifier la rétrogradation au Freemium

### Interface
1. Se connecter comme owner → voir la page Abonnement
2. Vérifier les barres de progression d'utilisation
3. Cliquer "Mettre à niveau" → vérifier la redirection Stripe
4. Se connecter comme Admin → vérifier les Resources Plan et Subscription
5. Vérifier le widget PlanUsageWidget sur le dashboard
