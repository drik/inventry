<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->renameColumn('vendor_id', 'supplier_id');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreign('model_id')->references('id')->on('asset_models')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['model_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->renameColumn('supplier_id', 'vendor_id');
        });
    }
};
