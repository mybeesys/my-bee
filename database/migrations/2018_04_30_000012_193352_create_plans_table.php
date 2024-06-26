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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->enum('span', ['one-time', 'specified']);
            $table->integer('span_in_days');
            $table->decimal('price', 19,4);
            $table->integer('max_allowed_companies');
            $table->integer('max_allowed_users');
            $table->integer('max_allowed_purchase_invoices');
            $table->integer('max_allowed_sales_invoices');
            $table->integer('restrict_account_after_days');

//            $table->integer('ads_availability_in_hours');
//            $table->integer('maximum_ad_views');
//            $table->integer('sms_marketing_count');
//            $table->integer('email_marketing_count');
//            $table->integer('landing_page_marketing_active_count');
//            $table->boolean('sms_marketing');
//            $table->boolean('email_marketing');
//            $table->boolean('landing_page_marketing');
//            $table->boolean('crm');
//            $table->boolean('hr');
//            $table->boolean('seo');
            $table->boolean('active')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
