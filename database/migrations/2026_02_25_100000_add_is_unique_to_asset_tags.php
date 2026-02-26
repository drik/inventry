<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_tags', function (Blueprint $table) {
            $table->boolean('is_unique')->default(false)->after('is_system');
        });

        // Set system tags (Serial Number, SKU) as unique by default
        DB::table('asset_tags')
            ->where('is_system', true)
            ->update(['is_unique' => true]);
    }

    public function down(): void
    {
        Schema::table('asset_tags', function (Blueprint $table) {
            $table->dropColumn('is_unique');
        });
    }
};
