<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('postal_code')->nullable()->after('email');
            $table->foreignId('state_id')->nullable()->change();
            $table->foreignId('city_id')->nullable()->change();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('trn')->nullable()->after('email');
            $table->string('postal_code')->nullable()->after('trn');
            $table->foreignId('state_id')->nullable()->after('postal_code')->constrained('states');
            $table->foreignId('city_id')->nullable()->after('state_id')->constrained('cities');
            $table->foreignId('area_id')->nullable()->after('city_id')->constrained('areas');
            $table->string('delivery_address')->nullable()->after('area_id');
        });

        DB::table('suppliers')
            ->whereNotNull('address')
            ->whereNull('delivery_address')
            ->update(['delivery_address' => DB::raw('`address`')]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('postal_code');
            $table->foreignId('state_id')->nullable(false)->change();
            $table->foreignId('city_id')->nullable(false)->change();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('state_id');
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['trn', 'postal_code', 'delivery_address']);
        });
    }
};
