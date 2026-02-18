<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->json('custom_fields_schema')->nullable();
            $table->string('depreciation_method')->nullable();
            $table->integer('default_useful_life_months')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'name', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
