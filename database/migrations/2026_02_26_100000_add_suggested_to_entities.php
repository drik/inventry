<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['asset_categories', 'manufacturers', 'asset_models', 'locations', 'suppliers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('suggested')->nullable()->default(null);
            });
        }
    }

    public function down(): void
    {
        foreach (['asset_categories', 'manufacturers', 'asset_models', 'locations', 'suppliers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('suggested');
            });
        }
    }
};
