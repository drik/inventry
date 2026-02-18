<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('scope_type')->default('all');
            $table->json('scope_ids')->nullable();
            $table->foreignUlid('created_by')->constrained('users');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('total_expected')->default(0);
            $table->unsignedInteger('total_scanned')->default(0);
            $table->unsignedInteger('total_matched')->default(0);
            $table->unsignedInteger('total_missing')->default(0);
            $table->unsignedInteger('total_unexpected')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sessions');
    }
};
