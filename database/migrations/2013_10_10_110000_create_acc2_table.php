<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcc2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('acc2', function (Blueprint $table) {
            $table->id();
            $table->integer('code')->index();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->string('name');
            $table->integer('acc1_code')->index();
            $table->foreign('acc1_code')->references('code')->on('acc1');
            $table->boolean('editable')->default(0);
            $table->boolean('deletable')->default(0);
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
        Schema::dropIfExists('acc2s');
    }
}
