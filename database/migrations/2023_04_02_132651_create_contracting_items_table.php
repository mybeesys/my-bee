<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contracting_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->foreignId('contracting_item_category_id')->references('id')->on('contracting_item_categories');
            $table->string('name');
            $table->foreignId('small_unit_id')->nullable()->references('id')->on('units');
            $table->foreignId('large_unit_id')->nullable()->references('id')->on('units');
            $table->integer('units_count_from_small')->nullable();
            $table->decimal('small_unit_price')->nullable();
            $table->decimal('large_unit_price')->nullable();
            $table->decimal('lift_price')->nullable();
            $table->decimal('down_price')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('contracting_items');
    }
};
