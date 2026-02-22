<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlanFeature: string implements HasLabel
{
    // Numeric limits
    case MaxOrganizations = 'max_organizations';
    case MaxUsers = 'max_users';
    case MaxAssets = 'max_assets';
    case MaxLocations = 'max_locations';
    case MaxActiveInventorySessions = 'max_active_inventory_sessions';
    case MaxTasksPerSession = 'max_tasks_per_session';
    case MaxAiRequestsDaily = 'max_ai_requests_daily';
    case MaxAiRequestsMonthly = 'max_ai_requests_monthly';

    // Boolean features
    case HasApiAccess = 'has_api_access';
    case HasCustomIntegrations = 'has_custom_integrations';
    case HasAdvancedAnalytics = 'has_advanced_analytics';
    case HasPrioritySupport = 'has_priority_support';
    case HasExport = 'has_export';

    public function getLabel(): string
    {
        return match ($this) {
            self::MaxOrganizations => 'Organisations',
            self::MaxUsers => 'Utilisateurs',
            self::MaxAssets => 'Actifs',
            self::MaxLocations => 'Sites',
            self::MaxActiveInventorySessions => 'Sessions d\'inventaire actives',
            self::MaxTasksPerSession => 'Tâches par session',
            self::MaxAiRequestsDaily => 'Requêtes IA / jour',
            self::MaxAiRequestsMonthly => 'Requêtes IA / mois',
            self::HasApiAccess => 'Accès API mobile',
            self::HasCustomIntegrations => 'Intégrations sur mesure',
            self::HasAdvancedAnalytics => 'Analyses avancées',
            self::HasPrioritySupport => 'Support prioritaire',
            self::HasExport => 'Export de données',
        };
    }

    public function isNumericLimit(): bool
    {
        return in_array($this, [
            self::MaxOrganizations,
            self::MaxUsers,
            self::MaxAssets,
            self::MaxLocations,
            self::MaxActiveInventorySessions,
            self::MaxTasksPerSession,
            self::MaxAiRequestsDaily,
            self::MaxAiRequestsMonthly,
        ]);
    }

    public function isBooleanFeature(): bool
    {
        return ! $this->isNumericLimit();
    }

    public function getCountableModel(): ?string
    {
        return match ($this) {
            self::MaxUsers => \App\Models\User::class,
            self::MaxAssets => \App\Models\Asset::class,
            self::MaxLocations => \App\Models\Location::class,
            self::MaxActiveInventorySessions => \App\Models\InventorySession::class,
            self::MaxTasksPerSession => \App\Models\InventoryTask::class,
            self::MaxAiRequestsDaily, self::MaxAiRequestsMonthly => \App\Models\AiUsageLog::class,
            default => null,
        };
    }
}
