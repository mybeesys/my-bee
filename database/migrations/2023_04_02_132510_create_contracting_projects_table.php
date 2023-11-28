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
        Schema::create('contracting_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->string('name');
            $table->enum('type', ['main', 'sub']);
            $table->foreignId('contracting_main_project_id')->nullable()->references('id')->on('contracting_projects');
            $table->foreignId('status_id')->references('id')->on('contracting_project_statuses');
            $table->foreignId('client_id')->references('id')->on('clients');
            $table->foreignId('contracting_project_category_id')->references('id')->on('contracting_project_categories');
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
        Schema::dropIfExists('contracting_projects');
    }
};
