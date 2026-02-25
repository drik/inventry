<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('mediable_type');
            $table->ulid('mediable_id');
            $table->string('collection'); // photos, audio, video, documents
            $table->string('disk')->default('s3');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->json('metadata')->nullable();
            $table->foreignUlid('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['mediable_type', 'mediable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
