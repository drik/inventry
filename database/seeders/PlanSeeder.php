<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Freemium',
                'slug' => 'freemium',
                'description' => 'Plan gratuit pour découvrir Inventry avec des fonctionnalités de base.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'sort_order' => 0,
                'paddle_monthly_price_id' => null,
                'paddle_yearly_price_id' => null,
                'limits' => [
                    'max_organizations' => 1,
                    'max_users' => 3,
                    'max_assets' => 25,
                    'max_locations' => 1,
                    'max_active_inventory_sessions' => 1,
                    'max_tasks_per_session' => 2,
                    'max_ai_requests_daily' => 2,
                    'max_ai_requests_monthly' => 10,
                    'has_api_access' => true,
                    'has_custom_integrations' => false,
                    'has_advanced_analytics' => false,
                    'has_priority_support' => false,
                    'has_export' => false,
                ],
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Pour les petites équipes qui ont besoin de gérer leur inventaire efficacement.',
                'price_monthly' => 500,
                'price_yearly' => 5000,
                'sort_order' => 1,
                'paddle_monthly_price_id' => null, // Replace with real Paddle price ID
                'paddle_yearly_price_id' => null,  // Replace with real Paddle price ID
                'limits' => [
                    'max_organizations' => 1,
                    'max_users' => 10,
                    'max_assets' => 200,
                    'max_locations' => 5,
                    'max_active_inventory_sessions' => 3,
                    'max_tasks_per_session' => 5,
                    'max_ai_requests_daily' => 5,
                    'max_ai_requests_monthly' => 50,
                    'has_api_access' => true,
                    'has_custom_integrations' => false,
                    'has_advanced_analytics' => false,
                    'has_priority_support' => false,
                    'has_export' => true,
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Pour les entreprises en croissance avec des besoins avancés de gestion d\'inventaire.',
                'price_monthly' => 3500,
                'price_yearly' => 35000,
                'sort_order' => 2,
                'paddle_monthly_price_id' => null, // Replace with real Paddle price ID
                'paddle_yearly_price_id' => null,  // Replace with real Paddle price ID
                'limits' => [
                    'max_organizations' => 3,
                    'max_users' => 50,
                    'max_assets' => 2000,
                    'max_locations' => 20,
                    'max_active_inventory_sessions' => 10,
                    'max_tasks_per_session' => 20,
                    'max_ai_requests_daily' => 50,
                    'max_ai_requests_monthly' => 500,
                    'has_api_access' => true,
                    'has_custom_integrations' => false,
                    'has_advanced_analytics' => true,
                    'has_priority_support' => false,
                    'has_export' => true,
                ],
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Usage illimité avec support prioritaire et intégrations sur mesure.',
                'price_monthly' => 25000,
                'price_yearly' => 250000,
                'sort_order' => 3,
                'paddle_monthly_price_id' => null, // Replace with real Paddle price ID
                'paddle_yearly_price_id' => null,  // Replace with real Paddle price ID
                'limits' => [
                    'max_organizations' => -1,
                    'max_users' => -1,
                    'max_assets' => -1,
                    'max_locations' => -1,
                    'max_active_inventory_sessions' => -1,
                    'max_tasks_per_session' => -1,
                    'max_ai_requests_daily' => -1,
                    'max_ai_requests_monthly' => -1,
                    'has_api_access' => true,
                    'has_custom_integrations' => true,
                    'has_advanced_analytics' => true,
                    'has_priority_support' => true,
                    'has_export' => true,
                ],
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData,
            );
        }
    }
}
