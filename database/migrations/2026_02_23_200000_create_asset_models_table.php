<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_models', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('model_number')->nullable();
            $table->foreignUlid('category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->foreignUlid('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('end_of_life_months')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_models');
    }
};
