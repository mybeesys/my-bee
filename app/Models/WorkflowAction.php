<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowAction extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'firebase_topics' => 'array',
        'firebase_recipients' => 'array',
        'notify_control_panel_alert_recipients' => 'array',
        'sms_recipients' => 'array',
        'sms_recipients_phones' => 'array',
        'emails_recipients' => 'array',
        'emails_users_recipients' => 'array',
        'whatsapp_recipients' => 'array',
        'telegram_recipients' => 'array',
        'notifiable_relations' => 'array',
        'notifiable_users' => 'array',
        'push_notification_include_data' => 'array',
    ];

    const ACTION_SEND_SMS = "send-sms";
    const ACTION_NOTIFY_CONTROL_PANEL_USER = "notify-control-panel-user";
    const ACTION_PUSH_NOTIFICATION = "push-notification";

    public function workflow(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function executions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkflowActionExecution::class)->latest();
    }
}
