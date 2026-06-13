<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_offer_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->foreignId('price_offer_id')->index()->references('id')->on('price_offers');
            $table->foreignId('tax_profile_id')->nullable()->index()->references('id')->on('tax_profiles');

            $table->morphs('item');
            $table->integer('qty');
            $table->decimal('unit_price', 21, 8);
            $table->decimal('discount', 21, 8)->default(0);
            $table->decimal('tax', 21, 8)->default(0);

            $table->foreignId('user_id')->index()->references('id')->on('users');

            $table->text('tax_profile_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_offer_details');
    }
};
