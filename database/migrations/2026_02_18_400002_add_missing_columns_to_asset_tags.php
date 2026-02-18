<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_tags', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_tags', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('asset_tags', 'is_required')) {
                $table->boolean('is_required')->default(false)->after('description');
            }
            if (! Schema::hasColumn('asset_tags', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_tags', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_required', 'sort_order']);
        });
    }
};
