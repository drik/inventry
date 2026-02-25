<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign key first (MySQL needs this before dropping the unique index it relies on)
        Schema::table('asset_conditions', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        // Now drop the unique constraint
        Schema::table('asset_conditions', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'slug']);
        });

        // Make organization_id nullable (null = global/default)
        Schema::table('asset_conditions', function (Blueprint $table) {
            $table->foreignUlid('organization_id')->nullable()->change();
        });

        // Add new unique constraint that allows null org_id
        Schema::table('asset_conditions', function (Blueprint $table) {
            $table->unique(['organization_id', 'slug']);
        });

        // Delete any existing per-org default conditions (will be replaced by global ones)
        DB::table('asset_conditions')->where('is_default', true)->delete();
    }

    public function down(): void
    {
        Schema::table('asset_conditions', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'slug']);
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete()->change();
            $table->unique(['organization_id', 'slug']);
        });
    }
};
