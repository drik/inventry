<?php

require __DIR__ . '/vendor/autoload.php';

 = require_once __DIR__ . '/bootstrap/app.php';
 = ->make(Illuminate\Contracts\Console\Kernel::class);
->bootstrap();

 = App\Models\Organization::whereDoesntHave('assetConditions')->get();
echo 'Organisations sans conditions : ' . ->count() . PHP_EOL;

foreach ( as ) {
    foreach (App\Models\AssetCondition::getDefaultConditions() as ) {
        App\Models\AssetCondition::withoutGlobalScopes()->create([
            'organization_id' => ->id,
            'is_default' => true,
            ...,
        ]);
    }
    echo 'OK: ' . ->name . PHP_EOL;
}

echo 'Terminé.' . PHP_EOL;
