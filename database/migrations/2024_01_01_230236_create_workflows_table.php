<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->enum('status', ['idle', 'running'])->default('idle');
            $table->text('description');
            $table->enum('trigger', ['model', 'custom']);
            $table->string('model_type')->index()->nullable(); // when trigger is model, NOTE: model_id should be in executions because every execution happens on a different model
            $table->enum('model_event', ['none', 'created', 'updated', 'deleted']); //none when the trigger is custom
            $table->enum('model_comparison', ['any-attribute', 'specified'])->default('any-attribute'); // e.g. in case of record updated run only when status is updated
            $table->string('model_attribute')->index()->nullable(); //when model comparison is specified
            $table->string('custom_trigger')->nullable();
            $table->enum('role_usage', ['not-supported', 'any-role', 'specified'])->default('not-supported');
            $table->json('roles_names')->nullable(); // role_usage(specified), if model uses has roles trait, specify a roles
            $table->enum('condition_type', ['no-condition-is-required', 'all-conditions-are-true', 'any-condition-is-true']);
            $table->boolean('visible')->default(1);
            $table->boolean('active')->default(0);
            $table->boolean('requires_approval_for_each_execution')->default(0);
            $table->text('logs')->nullable();
            $table->text('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('workflow_id')->index()->references('id')->on('workflows');
            $table->enum('operator', ['is-equal-to', 'is-not-equal-to', 'equals-or-greater-than', 'equals-or-less-than', 'greater-than', 'less-than']);
            $table->string('model_attribute'); //column
            $table->string('compare_value'); // the value to compare
            $table->timestamps();
        });

        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('workflow_id')->index()->references('id')->on('workflows');
            $table->unsignedInteger('sort')->default(1);
            $table->enum('action', ['add-tag', 'send-firebase-notification', 'notify-control-panel-user', 'send-sms', 'send-bulk-sms', 'send-email', 'send-whatsapp-message', 'send-telegram-message', 'push-notification']);
            $table->string('tag')->nullable(); //add tag action
            $table->json('firebase_topics')->nullable(); // array of topics
            $table->json('firebase_recipients')->nullable(); // array of user ids
            $table->json('notify_control_panel_alert_recipients')->nullable(); // array of user ids
            $table->string('notify_control_panel_alert_status')->nullable(); // success, warning, danger
            $table->text('notify_control_panel_alert_title')->nullable(); // array of user ids
            $table->text('notify_control_panel_alert_body')->nullable(); // array of user ids
            $table->boolean('notify_control_panel_broadcast')->nullable(); //
            $table->string('sms_provider_class')->nullable();
            $table->json('sms_recipients')->nullable(); // array of user ids
            $table->json('sms_recipients_phones')->nullable(); // array of phones
            $table->text('sms_message')->nullable();
            $table->bigInteger('bulk_sms_id')->nullable(); //execute
            $table->bigInteger('bulk_notification_id')->nullable(); //execute
            $table->json('emails_recipients')->nullable(); // array of emails
            $table->json('emails_users_recipients')->nullable(); // array of user ids
            $table->json('whatsapp_recipients')->nullable(); // array of phones
            $table->json('telegram_recipients')->nullable(); // array of phones
            $table->text('notifiable_relations')->nullable(); // array of relations ['user', 'other model that has notifiable trait']
            $table->text('notifiable_users')->nullable(); //array of users ids
            $table->text('push_notification_include_data')->nullable(); //array of anything, included data to the push act
            $table->text('notifiable_token_attribute_name')->nullable(); //fcm_token
            $table->timestamps();
        });

        Schema::create('workflow_action_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('workflow_action_id')->index()->references('id')->on('workflow_actions')->cascadeOnDelete();
            $table->bigInteger('model_id')->unsigned()->nullable();
            $table->string('execution_time')->nullable();
            $table->boolean('required_approval')->default(0);
            $table->foreignId('approved_by')->nullable()->index()->references('id')->on('users')->cascadeOnDelete();
            $table->text('logs')->nullable();
            $table->text('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_action_pending_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('workflow_action_id')->index()->references('id')->on('workflow_actions')->cascadeOnDelete();
            $table->bigInteger('model_id')->unsigned()->nullable();
            $table->string('execution_time')->nullable();
            $table->text('logs')->nullable();
            $table->text('meta')->nullable();
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
        Schema::dropIfExists('workflow_action_executions');
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('workflow_conditions');
        Schema::dropIfExists('workflows');
    }
};
