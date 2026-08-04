<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('max_allowed_expenses')->default(-1)->after('max_allowed_supply_orders');
        });

        Plan::query()->where('code', Plan::CODE_FREE)->update([
            'max_allowed_expenses' => 5,
        ]);

        Plan::query()->whereIn('code', [Plan::CODE_BUSINESS, Plan::CODE_COMPLETE])->update([
            'max_allowed_expenses' => -1,
        ]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_allowed_expenses');
        });
    }
};
