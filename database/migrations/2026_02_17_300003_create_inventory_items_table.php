<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('session_id')->constrained('inventory_sessions')->cascadeOnDelete();
            $table->foreignUlid('task_id')->nullable()->constrained('inventory_tasks')->nullOnDelete();
            $table->foreignUlid('asset_id')->nullable()->constrained('assets');
            $table->string('status')->default('expected');
            $table->dateTime('scanned_at')->nullable();
            $table->foreignUlid('scanned_by')->nullable()->constrained('users');
            $table->text('condition_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
