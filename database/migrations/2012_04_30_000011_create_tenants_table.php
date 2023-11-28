<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->index()->references('id')->on('clients');
            $table->enum('type', ['company', 'individual'])->index();
            $table->string('name');
            $table->string('slug')->index()->unique();
            $table->string('phone');
            $table->string('mobile')->nullable();
            $table->string('email');
            $table->string('address')->nullable();
            $table->string('trn')->nullable(); //tax registration number
            $table->string('company_person')->nullable();
            $table->boolean('active')->default(1);
            $table->timestamps();
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('user_id')->index()->references('id')->on('users');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('tenant_user');
    }
}
