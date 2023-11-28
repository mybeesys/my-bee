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
            $table->foreignId('supplier_id')->index()->nullable()->references('id')->on('suppliers');
            $table->integer('acc4_code')->nullable()->index();
            $table->foreign('acc4_code')->references('code')->on('acc4');
            $table->decimal('amount', 19, 4)->unsigned();
            $table->date('date')->index();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
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
