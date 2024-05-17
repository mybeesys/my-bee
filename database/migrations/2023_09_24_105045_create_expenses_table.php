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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->foreignId('expense_category_id')->index()->references('id')->on('expense_categories');
            $table->foreignId('tax_profile_id')->nullable()->index()->references('id')->on('tax_profiles');

            $table->decimal('amount', 21, 6)->unsigned();
            $table->decimal('tax', 21, 6)->default(0);

            $table->date('date')->index();

            $table->text('description')->nullable();

            $table->text('attributes')->nullable();
            $table->text('meta')->nullable();

            $table->text('tax_profile_data')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
