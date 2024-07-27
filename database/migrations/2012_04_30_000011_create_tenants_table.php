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

            //store settings
            $table->string('store_title_en')->nullable();
            $table->string('store_title_ar')->nullable();
            $table->text('store_bio_en')->nullable();
            $table->text('store_bio_ar')->nullable();
            $table->string('store_address_en')->nullable();
            $table->string('store_address_ar')->nullable();
            $table->string('store_working_hours_en')->nullable();
            $table->string('store_working_hours_ar')->nullable();
            $table->boolean('store_hide_out_of_stock_products')->default(0);
            $table->boolean('store_enable_orders_tracking')->default(0);
            $table->boolean('store_enable_stock_tracking')->default(0);
            $table->enum('store_orders_tracking_mode', ['automatic', 'manually'])->nullable();
            $table->integer('store_orders_tracking_packaging_time_hours')->nullable();
            $table->integer('store_orders_tracking_delivery_time_hours')->nullable();
            $table->text('store_social_media_links')->nullable();
            $table->text('store_theme')->nullable();
            $table->text('store_terms_and_conditions')->nullable();

            //logo and cover (Spatie file upload)
            //store settings

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
