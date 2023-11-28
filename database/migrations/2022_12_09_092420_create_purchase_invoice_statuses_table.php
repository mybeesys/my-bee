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
        Schema::create('purchase_invoice_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->string('name');
            $table->string('name_for_client')->nullable();
            $table->text('description')->nullable();
            $table->string('color');
            $table->boolean('all_supervisors_can_change_to_status')->default(0);
            $table->boolean('lock_change')->default(0); //specify who can change from this status
            $table->boolean('lock_name')->default(0);
            $table->boolean('default')->default(0);
            $table->boolean('system')->default(0);
            $table->boolean('locks_invoice')->default(0);
            $table->boolean('releases_stock')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_statuses');
    }
};
