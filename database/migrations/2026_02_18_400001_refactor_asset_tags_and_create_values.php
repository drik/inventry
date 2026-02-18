<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop the polymorphic pivot table (no longer needed)
        Schema::dropIfExists('taggables');

        // 2. Clear existing tags (old schema is incompatible with new category-based structure)
        DB::table('asset_tags')->truncate();

        // 3. Modify asset_tags: remove old columns, add new ones
        Schema::table('asset_tags', function (Blueprint $table) {
            if (Schema::hasColumn('asset_tags', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('asset_tags', 'color')) {
                $table->dropColumn('color');
            }
        });

        if (! Schema::hasColumn('asset_tags', 'category_id')) {
            Schema::table('asset_tags', function (Blueprint $table) {
                $table->foreignUlid('category_id')
                    ->after('organization_id')
                    ->constrained('asset_categories')
                    ->cascadeOnDelete();
                $table->text('description')->nullable()->after('name');
                $table->boolean('is_required')->default(false)->after('description');
                $table->integer('sort_order')->default(0)->after('is_required');
            });
        }

        // 4. Create asset_tag_values table
        if (! Schema::hasTable('asset_tag_values')) {
            Schema::create('asset_tag_values', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->foreignUlid('asset_tag_id')->constrained('asset_tags')->cascadeOnDelete();
                $table->string('value');
                $table->timestamps();

                $table->unique(['asset_id', 'asset_tag_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_tag_values');

        Schema::table('asset_tags', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'description', 'is_required', 'sort_order']);
        });

        Schema::table('asset_tags', function (Blueprint $table) {
            $table->string('type')->nullable();
            $table->string('color')->nullable();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignUlid('asset_tag_id')->constrained('asset_tags')->cascadeOnDelete();
            $table->ulidMorphs('taggable');
            $table->primary(['asset_tag_id', 'taggable_id', 'taggable_type']);
        });
    }
};
