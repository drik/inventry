<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('notable_type');
            $table->ulid('notable_id');
            $table->text('content');
            $table->text('original_content')->nullable();
            $table->string('source_type')->default('text'); // text, ai_rephrase, ai_photo_desc, ai_audio_transcript, ai_video_desc
            $table->foreignUlid('source_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignUlid('ai_usage_log_id')->nullable()->constrained('ai_usage_logs')->nullOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['notable_type', 'notable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_notes');
    }
};
