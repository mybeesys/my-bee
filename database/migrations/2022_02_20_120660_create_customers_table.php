<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->string('no');
            $table->string('name');
            $table->string('phone')->index();
            $table->string('delivery_address')->nullable();
            $table->string('email')->index()->nullable();
            $table->string('notes')->nullable();
            $table->string('trn')->nullable();
            $table->foreignId('state_id')->nullable()->index()->references('id')->on('states');
            $table->foreignId('city_id')->nullable()->index()->references('id')->on('cities');
            $table->foreignId('area_id')->nullable()->index()->references('id')->on('areas');
            $table->boolean('auto_registered')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
