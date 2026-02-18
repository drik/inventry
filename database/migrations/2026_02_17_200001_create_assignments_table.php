<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets');
            $table->string('assignee_type');
            $table->char('assignee_id', 26);
            $table->foreignUlid('assigned_by')->constrained('users');
            $table->dateTime('assigned_at');
            $table->date('expected_return_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->text('return_condition')->nullable();
            $table->foreignUlid('return_accepted_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamps();

            $table->index(['assignee_type', 'assignee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
