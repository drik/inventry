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
            $table->text('system_prompt')->nullable()->after('use_case');
            $table->text('user_prompt')->nullable()->after('system_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('ai_recognition_logs', function (Blueprint $table) {
            $table->dropColumn(['system_prompt', 'user_prompt']);
        });
    }
};
