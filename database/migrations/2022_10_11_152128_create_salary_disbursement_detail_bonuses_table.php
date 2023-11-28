<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalaryDisbursementDetailBonusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('salary_disbursement_detail_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_id')->references('id')->on('salary_disbursement_details');
            $table->string('name');
            $table->decimal('amount');
            $table->text('description')->nullable();
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
        Schema::dropIfExists('salary_disbursement_detail_bonuses');
    }
}
