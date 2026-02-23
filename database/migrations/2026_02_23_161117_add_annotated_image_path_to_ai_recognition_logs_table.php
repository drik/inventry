<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_recognition_logs', function (Blueprint $table) {
            $table->string('annotated_image_path')->nullable()->after('captured_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('ai_recognition_logs', function (Blueprint $table) {
            $table->dropColumn('annotated_image_path');
        });
    }
};
