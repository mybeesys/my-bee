<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlatformCouponsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('platform_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('span', ['one_time', 'specified_time', 'unlimited_time']);
            $table->enum('type', ['discount', 'add_free_subscription_days']);
            $table->integer('value');
            $table->dateTime('valid_until');
            $table->longText('description')->nullable();
            $table->boolean('active')->default(1);
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
        Schema::dropIfExists('coupons');
    }
}
