<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('original_price_ex_tax', 21, 8)->nullable()->after('discount_amount');
            $table->decimal('original_tax_amount', 21, 8)->nullable()->after('original_price_ex_tax');
            $table->decimal('original_price', 21, 8)->nullable()->after('original_tax_amount');
            $table->decimal('admin_discount_percent', 8, 4)->nullable()->after('original_price');
            $table->decimal('admin_discount_amount', 21, 8)->nullable()->after('admin_discount_percent');
            $table->string('admin_discount_note', 500)->nullable()->after('admin_discount_amount');
            $table->timestamp('admin_discounted_at')->nullable()->after('admin_discount_note');
            $table->foreignId('admin_discounted_by')->nullable()->after('admin_discounted_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_discounted_by');
            $table->dropColumn([
                'original_price_ex_tax',
                'original_tax_amount',
                'original_price',
                'admin_discount_percent',
                'admin_discount_amount',
                'admin_discount_note',
                'admin_discounted_at',
            ]);
        });
    }
};
