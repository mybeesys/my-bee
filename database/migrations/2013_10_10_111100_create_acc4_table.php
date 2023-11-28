<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcc4Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('acc4', function (Blueprint $table) {
            $table->id();
            $table->integer('code')->index();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->nullableMorphs('item');
            $table->integer('acc3_code')->index();
            $table->foreign('acc3_code')->references('code')->on('acc3');
            $table->string('name')->nullable();
            $table->text('files')->nullable();
            $table->text('meta')->nullable();
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
        Schema::dropIfExists('acc4s');
    }
}
