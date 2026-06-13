<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index()->references('id')->on('tenants');
            $table->unsignedInteger('sort')->default(1)->index();
            $table->json('display_name');
            $table->string('key')->index();
            $table->text('value')->nullable();
            $table->text('def_val')->nullable();
            $table->enum('type', ['text', 'rich-text', 'text-area', 'bool', 'toggle', 'file', 'options', 'repeater', 'products-discount'])->index();
            $table->text('rules')->nullable();
            $table->string('placeholder')->nullable();
            $table->string('helper_text')->nullable();
            $table->string('tab')->index()->nullable();
            $table->unsignedInteger('tab_sort')->default(1)->index();
            $table->string('group')->nullable();
            $table->text('options')->nullable();
            $table->boolean('deletable')->index()->default(0);
            $table->text('repeater_fields')->nullable(); // sort, display_name, required, value
            $table->boolean('is_password')->index()->default(0);
            $table->boolean('visible_in_user_friendly_settings')->index()->default(1);
            $table->string('options_cache_key')->nullable();
            $table->string('disable_options_when_tables_has_data')->nullable();
            $table->timestamps();

//            $table->unique(['key', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settings');
    }
}
