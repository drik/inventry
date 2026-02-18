<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code');
            $table->string('name');
            $table->foreignUlid('category_id')->constrained('asset_categories');
            $table->foreignUlid('model_id')->nullable();
            $table->foreignUlid('location_id')->constrained('locations');
            $table->foreignUlid('department_id')->nullable()->constrained('departments');
            $table->string('serial_number')->nullable();
            $table->string('sku')->nullable();
            $table->string('status')->default('available');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->foreignUlid('vendor_id')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->string('depreciation_method')->nullable();
            $table->integer('useful_life_months')->nullable();
            $table->decimal('salvage_value', 12, 2)->nullable();
            $table->date('retirement_date')->nullable();
            $table->string('barcode');
            $table->string('qr_code_path')->nullable();
            $table->text('notes')->nullable();
            $table->json('custom_field_values')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'asset_code']);
            $table->unique(['organization_id', 'barcode']);
            $table->index(['organization_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
