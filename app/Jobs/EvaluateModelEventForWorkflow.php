<?php

namespace App\Jobs;

use App\Models\Workflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateModelEventForWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Model $model;

    public string $model_event;

    public array $model_changes;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Model $model, $model_event, $model_changes = [])
    {
        $this->queue = "workflow-evaluator";
        $this->model = $model;
        $this->model_event = $model_event;
        $this->model_changes = $model_changes;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        $workflows = Workflow::with(['conditions', 'actions'])
            ->where('model_type', get_class($this->model))
            ->where('model_event', $this->model_event)
            ->get();

        // check conditions

        foreach ($workflows as $workflow) {

            if (!$workflow->active) {
                $this->logInWorkflow($workflow, self::getFormattedDate() . ", Workflow evaluator: skipped due to being inactive.");
                continue;
            }
            $conditionsMetAndShouldRun = $this->conditionsMet($workflow);
            if ($conditionsMetAndShouldRun) {
                dispatch(new ExecuteWorkflow($workflow, $this->model->id));
            }

            $this->logInWorkflow($workflow, self::getFormattedDate() . ", Workflow evaluator: finished, workflow #$workflow->id on trigger #$workflow->model_type #".$this->model->id);

        }
    }

    protected function conditionsMet(Workflow $workflow): bool
    {
        switch ($workflow->trigger) {
            case Workflow::TRIGGER_TYPE_MODEL:
            {

                $model_uses_roles_and_has_specified_roles = $workflow->role_usage == Workflow::ROLES_USAGE_SPECIFIED;
                $needs_condition_checking = $workflow->condition_type != Workflow::CONDITION_TYPE_NO_CONDITION_IS_REQUIRED;
                //Roles and conditions are not a factor, condition passes
                if (!$needs_condition_checking and !$model_uses_roles_and_has_specified_roles and $workflow->model_comparison === "any-attribute") {
                    $this->logInWorkflow($workflow, self::getFormattedDate() . ", Workflow evaluator: no conditions were required, workflow #$workflow->id on trigger #$workflow->model_type #" . $this->model->id);
                    return true;
                }

                if ($model_uses_roles_and_has_specified_roles) {
                    $qualifies = $this->model->hasAnyRole($workflow->roles_names ?? []);
                    if (!$qualifies) {
                        $this->logInWorkflow($workflow, self::getFormattedDate() . ", Workflow evaluator: model uses roles but does not qualify (roles do not match), workflow #$workflow->id on trigger #$workflow->model_type #" . $this->model->id . " does not require any conditions.");
                        return false;
                    }
                }


                if($workflow->model_comparison === "specified")
                {

                    $attributeChanged = array_key_exists($workflow->model_attribute, $this->model_changes);//$this->model->isDirty($workflow->model_attribute);

                    if($attributeChanged)
                    {
                        $this->logInWorkflow($workflow, self::getFormattedDate() . ", Workflow evaluator: model attribute (".$workflow->model_attribute.") was ".$this->model_event." , workflow #$workflow->id on trigger #$workflow->model_type #" . $this->model->id);
                    }else{
                        $this->logInWorkflow($workflow, self::getFormattedDate() . ", Workflow evaluator: model attribute (".$workflow->model_attribute.") was NOT ".$this->model_event." , workflow #$workflow->id on trigger #$workflow->model_type #" . $this->model->id);
                    }

                    if(!$attributeChanged)
                        return false;
                }

                if($workflow->condition_type == Workflow::CONDITION_TYPE_NO_CONDITION_IS_REQUIRED and $workflow->conditions->isEmpty())
                {
                    $this->logInWorkflow($workflow, self::getFormattedDate() . ", Workflow evaluator: no conditions were required, workflow #$workflow->id on trigger #$workflow->model_type #" . $this->model->id);
                    return true;
                }

                //check conditions

                $conditions_results = [];


                foreach ($workflow->conditions as $condition) {
                    if ($condition->operator == "is-equal-to")
                        $conditions_results[] = $this->model->{$condition->model_attribute} == $condition->compare_value;

                    if ($condition->operator == "is-not-equal-to")
                        $conditions_results[] = $this->model->{$condition->model_attribute} != $condition->compare_value;

                    if ($condition->operator == "equals-or-greater-than")
                        $conditions_results[] = $this->model->{$condition->model_attribute} >= $condition->compare_value;

                    if ($condition->operator == "equals-or-less-than")
                        $conditions_results[] = $this->model->{$condition->model_attribute} <= $condition->compare_value;

                    if ($condition->operator == "greater-than")
                        $conditions_results[] = $this->model->{$condition->model_attribute} > $condition->compare_value;

                    if ($condition->operator == "less-than")
                        $conditions_results[] = $this->model->{$condition->model_attribute} < $condition->compare_value;
                }

                if($workflow->condition_type == Workflow::CONDITION_TYPE_ALL_CONDITIONS_ARE_TRUE)
                {
                    $passes = !in_array(false, $conditions_results);

                    if(!$passes)
                    {
                        $this->logInWorkflow($workflow, self::getFormattedDate() . ", Workflow evaluator: some or all conditions were NOT met, workflow #$workflow->id on trigger #$workflow->model_type #".$this->model->id);
                    }
                    return $passes;
                }

                if($workflow->condition_type == Workflow::CONDITION_TYPE_ANY_CONDITION_IS_TRUE)
                {
                    $passes = in_array(true, $conditions_results);
                    if(!$passes)
                    {
                        $this->logInWorkflow($workflow, self::getFormattedDate() . ", Workflow evaluator: NONE of the conditions were met, workflow #$workflow->id on trigger #$workflow->model_type #".$this->model->id);
                    }
                    return $passes;
                }

                break;
            }
            case Workflow::TRIGGER_TYPE_CUSTOM:
            {
                throw new \Exception('Custom workflow triggers not supported yet.');
                break;
            }
            default:
            {
                throw new \Exception('Unknown workflow trigger');
            }
        }
        return false;
    }

    protected function logInWorkflow(Workflow $workflow, $log)
    {
        $workflow->refresh();
        $logs = $workflow->logs ?? [];
        $logs[] = $log;
        $workflow->update(['logs' => $logs]);
    }

    protected function getFormattedDate(): string
    {
        return now()->format('Y, j F, g:i a');
    }

}
