<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_offers', function (Blueprint $table) {
            $table->date('expires_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('price_offers', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
