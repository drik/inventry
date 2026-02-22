<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('identification_method')->default('barcode')->after('condition_notes');
            $table->foreignUlid('ai_recognition_log_id')->nullable()->after('identification_method')
                ->constrained('ai_recognition_logs')->nullOnDelete();
            $table->decimal('ai_confidence', 5, 4)->nullable()->after('ai_recognition_log_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_recognition_log_id');
            $table->dropColumn(['identification_method', 'ai_confidence']);
        });
    }
};
