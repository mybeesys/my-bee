<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('client_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('customer_id')->index()->references('id')->on('customers')->restrictOnDelete();;
            $table->enum('status', ['pending', 'processing', 'processed'])->default('pending');
            $table->string('subject');
            $table->text('report');
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('user_id')->index()->references('id')->on('users')->restrictOnDelete();
            $table->foreignId('processed_by')->nullable()->references('id')->on('users')->restrictOnDelete();
            $table->text('attributes')->nullable();
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
        Schema::dropIfExists('client_reports');
    }
}
