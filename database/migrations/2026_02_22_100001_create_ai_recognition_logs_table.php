<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_recognition_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('task_id')->nullable()->constrained('inventory_tasks')->nullOnDelete();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('captured_image_path');
            $table->string('use_case'); // 'identify', 'match', 'verify'
            $table->string('provider'); // 'gemini' or 'openai'
            $table->string('model');    // 'gemini-2.5-flash', 'gpt-4o', etc.
            $table->boolean('used_fallback')->default(false);
            $table->json('ai_response');
            $table->json('matched_asset_ids')->nullable();
            $table->foreignUlid('selected_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('selected_action')->nullable(); // 'matched', 'unexpected', 'dismissed'
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 8, 6)->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recognition_logs');
    }
};
