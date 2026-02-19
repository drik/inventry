<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_tags', function (Blueprint $table) {
            $table->string('encoding_mode')->nullable()->after('is_required');
        });

        Schema::table('asset_tag_values', function (Blueprint $table) {
            $table->string('encoding_mode')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('asset_tags', function (Blueprint $table) {
            $table->dropColumn('encoding_mode');
        });

        Schema::table('asset_tag_values', function (Blueprint $table) {
            $table->dropColumn('encoding_mode');
        });
    }
};
