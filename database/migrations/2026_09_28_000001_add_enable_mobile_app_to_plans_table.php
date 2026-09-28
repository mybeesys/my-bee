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
            $table->boolean('enable_mobile_app')->default(false)->after('enable_store');
        });

        Plan::query()->where('code', Plan::CODE_COMPLETE)->update([
            'enable_mobile_app' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('enable_mobile_app');
        });
    }
};
