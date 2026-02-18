<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_tag_values', function (Blueprint $table) {
            $table->string('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('asset_tag_values', function (Blueprint $table) {
            $table->string('value')->nullable(false)->change();
        });
    }
};
