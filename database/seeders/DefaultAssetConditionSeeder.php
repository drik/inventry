<?php

namespace Database\Seeders;

use App\Models\AssetCondition;
use Illuminate\Database\Seeder;

class DefaultAssetConditionSeeder extends Seeder
{
    public function run(): void
    {
        $conditions = AssetCondition::getDefaultConditions();

        foreach ($conditions as $condition) {
            AssetCondition::withoutGlobalScopes()->updateOrCreate(
                ['slug' => $condition['slug'], 'organization_id' => null],
                [
                    ...$condition,
                    'is_default' => true,
                ]
            );
        }

        $this->command->info('Default asset conditions seeded: ' . count($conditions));
    }
}
