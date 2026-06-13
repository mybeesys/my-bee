<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->where('status', 'sale_order')
            ->update(['status' => 'confirmed']);
    }

    public function down(): void
    {
        DB::table('invoices')
            ->where('status', 'confirmed')
            ->whereNull('locked_at')
            ->where('type', 'sales')
            ->update(['status' => 'sale_order']);
    }
};
