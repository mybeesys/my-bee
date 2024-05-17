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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->foreignId('service_type_id')->index()->references('id')->on('service_types');

            $table->morphs('item');

            $table->decimal('price', 21, 6);

            $table->text('description');

            $table->foreignId('tax_profile_id')->nullable()->index()->references('id')->on('tax_profiles');

            $table->text('tax_profile_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
