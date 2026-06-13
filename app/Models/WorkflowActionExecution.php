<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowActionExecution extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'logs' => 'array',
        'meta' => 'array',
    ];

    public function workflowAction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WorkflowAction::class);
    }

    public function getModelTypeAttribute()
    {
        $this->loadMissing('workflowAction.workflow');
        return $this->workflowAction->workflow->model_type;
    }
}
