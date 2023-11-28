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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->index()->references('id')->on('plans');
            $table->foreignId('client_id')->index()->references('id')->on('clients');
            $table->dateTime('start_date');
            $table->dateTime('next_billing_date')->nullable();
            $table->boolean('subscribed')->default(1);
            $table->boolean('expired')->default(0);
            $table->decimal('price', 19,4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
