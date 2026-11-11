<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['name', 'tenant_id']);
            $table->dropUnique(['barcode', 'tenant_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('category_id')
                ->constrained('suppliers')
                ->nullOnDelete();

            $table->unique(
                ['tenant_id', 'supplier_id', 'barcode'],
                'products_tenant_wholesaler_barcode_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_tenant_wholesaler_barcode_unique');
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['name', 'tenant_id']);
            $table->unique(['barcode', 'tenant_id']);
        });
    }
};
