<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1a. Make category_id nullable on asset_tags
        Schema::table('asset_tags', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        DB::statement('ALTER TABLE asset_tags MODIFY category_id CHAR(26) NULL');

        Schema::table('asset_tags', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('asset_categories')
                ->nullOnDelete();

            $table->boolean('is_system')->default(false)->after('encoding_mode');
        });

        // 1b. Create system tags per organization + migrate data
        $organizations = DB::table('organizations')->whereNull('deleted_at')->get();

        foreach ($organizations as $org) {
            $serialTagId = (string) Str::ulid();
            $skuTagId = (string) Str::ulid();
            $now = now();

            DB::table('asset_tags')->insert([
                [
                    'id' => $serialTagId,
                    'organization_id' => $org->id,
                    'category_id' => null,
                    'name' => 'Serial Number',
                    'description' => 'Manufacturer serial number',
                    'is_required' => false,
                    'encoding_mode' => 'qr_code',
                    'sort_order' => 1,
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => $skuTagId,
                    'organization_id' => $org->id,
                    'category_id' => null,
                    'name' => 'SKU',
                    'description' => 'Stock Keeping Unit',
                    'is_required' => false,
                    'encoding_mode' => 'ean_13',
                    'sort_order' => 2,
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            // 1c. Migrate serial_number values to AssetTagValue
            $assetsWithSerial = DB::table('assets')
                ->where('organization_id', $org->id)
                ->whereNotNull('serial_number')
                ->where('serial_number', '!=', '')
                ->get(['id', 'serial_number']);

            $serialBatch = [];
            foreach ($assetsWithSerial as $asset) {
                $serialBatch[] = [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $org->id,
                    'asset_id' => $asset->id,
                    'asset_tag_id' => $serialTagId,
                    'value' => $asset->serial_number,
                    'encoding_mode' => 'qr_code',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($serialBatch, 500) as $chunk) {
                DB::table('asset_tag_values')->insert($chunk);
            }

            // 1d. Migrate sku values to AssetTagValue
            $assetsWithSku = DB::table('assets')
                ->where('organization_id', $org->id)
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['id', 'sku']);

            $skuBatch = [];
            foreach ($assetsWithSku as $asset) {
                $skuBatch[] = [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $org->id,
                    'asset_id' => $asset->id,
                    'asset_tag_id' => $skuTagId,
                    'value' => $asset->sku,
                    'encoding_mode' => 'ean_13',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($skuBatch, 500) as $chunk) {
                DB::table('asset_tag_values')->insert($chunk);
            }
        }

        // 1e. Drop serial_number and sku columns from assets
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'serial_number']);
            $table->dropColumn(['serial_number', 'sku']);
        });
    }

    public function down(): void
    {
        // Re-add columns
        Schema::table('assets', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->after('manufacturer_id');
            $table->string('sku')->nullable()->after('serial_number');
            $table->index(['organization_id', 'serial_number']);
        });

        // Reverse-migrate data from tag values back to asset columns
        $systemTags = DB::table('asset_tags')->where('is_system', true)->get();

        foreach ($systemTags as $tag) {
            $values = DB::table('asset_tag_values')
                ->where('asset_tag_id', $tag->id)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->get(['asset_id', 'value']);

            $column = $tag->name === 'Serial Number' ? 'serial_number' : 'sku';

            foreach ($values as $val) {
                DB::table('assets')
                    ->where('id', $val->asset_id)
                    ->update([$column => $val->value]);
            }
        }

        // Delete system tag values and tags
        $systemTagIds = $systemTags->pluck('id')->toArray();
        DB::table('asset_tag_values')->whereIn('asset_tag_id', $systemTagIds)->delete();
        DB::table('asset_tags')->where('is_system', true)->delete();

        // Drop is_system column
        Schema::table('asset_tags', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });

        // Make category_id NOT NULL again
        Schema::table('asset_tags', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        DB::statement('ALTER TABLE asset_tags MODIFY category_id CHAR(26) NOT NULL');

        Schema::table('asset_tags', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('asset_categories')
                ->cascadeOnDelete();
        });
    }
};
