<?php

namespace App\Services;

use App\Models\BaseModel;
use App\Models\User;
use App\Traits\TrackWorkflowModelEvents;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class WorkflowService
{

    // Workflow is actions triggered where preconditions met

    protected array $available_actions = [
        'notify-control-panel-user' => 'Notify control panel user',
        'send-sms' => 'Send sms',
        'push-notification' => 'Push notification'
    ];

    protected array $hide_trigger_attributes = [
        'id',
        'uuid',
        'no',
        'password'
    ];


    public static function instance(): self
    {
        return new self();
    }

    public function getAvailableActions($asSelect = true): array
    {
        $actions = $this->available_actions;

        if ($asSelect) {
//            $actions = array_combine(array_values($this->available_actions), array_values($this->available_actions));
            $temp = [];
            foreach ($actions as $key => $value) {
                $temp[$key] = str($value)->replace('_', ' ')->ucfirst()->title()->value();
            }
            $actions = $temp;
        }
        return $actions;
    }

    public function listTriggers($asSelect = true): array
    {
        $data = [];
        $models_that_uses_track_workflow_model_events_trait = (new ClassService())->listModelsThatUses(TrackWorkflowModelEvents::class);

        if ($asSelect) {
            foreach ($models_that_uses_track_workflow_model_events_trait as $model_class) {
                $data[$model_class] = str(class_basename($model_class))->kebab()->replace('-', ' ')->title()->value();
            }
        } else {
            $data = $models_that_uses_track_workflow_model_events_trait;
        }
        return $data;
    }

    public function getTriggerAttributes($trigger_class, $asSelect = true, $withMutated = false): array
    {
        $model = new ($trigger_class);

        $attributes = Schema::getColumnListing($model->getTable());

        $attributes = array_diff($attributes, $this->hide_trigger_attributes);

        if ($withMutated)
            $attributes = array_merge($attributes, $this->getModelMutatedAttributes($trigger_class));

        if ($asSelect) {
            $attributes = array_combine(array_values($attributes), array_values($attributes));
            foreach ($attributes as $key => $value) {
                $mutated = $this->isModelAttributeMutated($trigger_class, $key) ? "- " : "";
                $attributes[$key] = str($mutated . $value)->replace('_', ' ')->ucfirst()->title()->value();
            }
        }

        return $attributes;
    }

    public function getTableColumnType($model_class, $column, $ignoreException = true): ?string
    {
        $table = (new ($model_class))->getTable();
        if ($ignoreException) {
            try {
                return Schema::getColumnType($table, $column);
            } catch (\Exception $exception) {
                return null;
            }
        }
        return Schema::getColumnType($table, $column);
    }

    public function modelHasRoles($model_class): bool
    {
        return (new ClassService())->classUses(HasRoles::class, $model_class);
    }

    public function listRoles($asSelect = true): array
    {
        if ($asSelect) {
            $roles = Role::all()->pluck('name', 'name')->toArray();
            foreach ($roles as $key => $value) {
                $roles[$key] = str($value)->replace(['_', '-'], ' ')->ucfirst()->title()->value();
            }
        } else {
            $roles = Role::all()->pluck('name')->toArray();
        }
        return $roles;
    }

    public function getModelMutatedAttributes($model_type): array
    {
        return \Cache::remember("mutated@$model_type", 60, function () use ($model_type) {
            return (new ($model_type))->getMutatedAttributes();
        });
    }

    public function isModelAttributeMutated($model_type, $attribute): bool
    {
        return in_array($attribute, self::getModelMutatedAttributes($model_type));
    }

    public function getModelRelatedRecipients($model_type): array
    {
        $rc = [];

        $reflector = new \ReflectionClass($model_type);

        $data = collect($reflector->getMethods())
            ->filter(
                fn($method) => !empty($method->getReturnType())
            )
//            ->pluck('name')
            ->all();

        foreach ($data as $item) {
            $rc[$item->name] = str($item->name)->title()->value();
        }
        return $rc;
    }

    /**
     *
     * Returns all the relationship methods defined
     * in the provided model class with related
     * model class and relation function name
     *
     * @param string $modelClass exampe: App\Models\Post
     * @return array $relattions array containing information about relationships
     */
    public function getModelRelationshipMethods(string $modelClass, $relation = "Illuminate\Database\Eloquent\Relations", $restrictToOnlyNotifiable = false): array
    {
        //can define this at class level
        $relationshipMethods = [
            'BelongsTo',
        ];

        $data = \Cache::remember("relations@$modelClass", 60, function () use ($modelClass, $relation) {
            $reflector = new \ReflectionClass($modelClass);

            return collect($reflector->getMethods())
                ->filter(
                    fn($method) => !empty($method->getReturnType()) &&
                        str_contains(
                            $method->getReturnType(),
                            $relation
                        )
                )
                ->pluck('name')
                ->all();
        });

//        array:2 [▼ // app\Services\WorkflowService.php:184
//  0 => "user"
//  1 => "country"
//]
        $items = [];

        foreach ($data as $key => $value) {
            $items[$value] = class_basename($modelClass)."->".$value;
        }

        if ($restrictToOnlyNotifiable) {
            //remove non Notifiable relations
            $model = ($modelClass)::with($data)->first();
            if ($model) {
                foreach ($data as $relation) {
                    if ($model->{$relation} and !ClassService::instance()->classUses(Notifiable::class, get_class($model->{$relation}))) {
                        unset($items[$relation]);
                    }
                }
            }
        }
        return $items;

    }
}
