<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('session_id')->constrained('inventory_sessions')->cascadeOnDelete();
            $table->foreignUlid('task_id')->nullable()->constrained('inventory_tasks')->nullOnDelete();
            $table->string('type'); // task_report, session_report
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('data')->nullable();
            $table->foreignUlid('generated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('pdf_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignUlid('excel_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reports');
    }
};
