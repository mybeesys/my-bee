<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'roles_names' => 'array',
        'logs' => 'array',
        'tags' => 'array',
    ];

    const TRIGGER_TYPE_MODEL = 'model';
    const TRIGGER_TYPE_CUSTOM = 'custom';

    const CONDITION_TYPE_NO_CONDITION_IS_REQUIRED = "no-condition-is-required";
    const CONDITION_TYPE_ALL_CONDITIONS_ARE_TRUE = "all-conditions-are-true";
    const CONDITION_TYPE_ANY_CONDITION_IS_TRUE = "any-condition-is-true";

    const ROLES_USAGE_NOT_SUPPORTED_BY_MODEL = 'not-supported';
    const ROLES_USAGE_ANY_ROLE = 'any-role';
    const ROLES_USAGE_SPECIFIED = 'specified';

    public function conditions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkflowCondition::class);
    }

    public function actions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkflowAction::class)->orderBy('sort');
    }

    public function executions(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(WorkflowActionExecution::class, WorkflowAction::class);
    }

    public function getActionsStatementAttribute(): string
    {
        $this->loadMissing('actions');
        return implode(', ', str_replace('-', ' ', $this->actions->pluck('action')->toArray()));
    }

    public function getStatementAttribute(): string
    {
        $description = null;
        $this->loadMissing('conditions');

        if ($this->trigger === Workflow::TRIGGER_TYPE_MODEL) {
            if ($this->role_usage === Workflow::ROLES_USAGE_NOT_SUPPORTED_BY_MODEL or $this->role_usage === Workflow::ROLES_USAGE_ANY_ROLE) {
                $description = "When " . class_basename($this->model_type)  . " " . $this->model_attribute . " " . $this->model_event;
            }

            if ($this->role_usage === Workflow::ROLES_USAGE_SPECIFIED) {
                $description = "When " . class_basename($this->model_type) . " " . $this->model_attribute . " " . " (" . implode(', ', $this->roles_names) . ") " . $this->model_event;
            }

            if ($this->conditions->isNotEmpty()) {
                $description = $description . ", with extra conditions";
            } else {
                $description = $description . ", no condition is required";
            }

            return $description;
        } else {
            $description = "No statement at this moment";
        }

        return $description;
    }
}
