<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowAction;
use App\Models\WorkflowActionExecution;
use App\Rules\InternationalPhoneRule;
use App\Rules\PhoneSignupRule;
use App\Services\ClassService;
use App\Services\SMSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ExecuteWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Workflow $workflow;
    protected $model_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Workflow $workflow, $model_id)
    {
        $this->queue = "workflow";
        $this->workflow = $workflow;
        $this->model_id = $model_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->runActions();
    }

    protected function runActions()
    {
        if ($this->workflow->actions->isEmpty()) {
            $this->logInWorkflow(self::getFormattedDate() . ", Workflow evaluator: no actions found, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id");
        }

        foreach ($this->workflow->actions as $workflowAction) {
            switch ($workflowAction->action) {
                case WorkflowAction::ACTION_SEND_SMS:
                {
                    $this->sendSms($workflowAction);
                    break;
                }

                case WorkflowAction::ACTION_NOTIFY_CONTROL_PANEL_USER:
                {
                    $this->notifyControlPanelUser($workflowAction);
                    break;
                }

                case WorkflowAction::ACTION_PUSH_NOTIFICATION:
                {
                    $this->pushNotification($workflowAction);
                    break;
                }

                default:
                {
                    $this->logInWorkflow(self::getFormattedDate() . ", Workflow evaluator: unsupported action, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id");
                }
            }
        }
    }

    protected function sendSms(WorkflowAction $workflowAction)
    {
        $startTime = microtime(true);

        if ($workflowAction->action !== WorkflowAction::ACTION_SEND_SMS)
            throw new \Exception("$workflowAction->action: Incompatible execution method.");


        $actionExecution = WorkflowActionExecution::create(
            [
                'tenant_id' => auth('sanctum')->id() ?? filament()->getTenant()->id,
                'workflow_action_id' => $workflowAction->id,
                'model_id' => $this->model_id,
                'execution_time' => null,
                'logs' => null,
                'meta' => null,
            ]
        );

        $logs = [];
        $meta = [];


        //get plain phones
        $phones = collect($workflowAction->sms_recipients_phones)->pluck('phone')->toArray();

        //get phones from users
        foreach ($workflowAction->sms_recipients ?? [] as $user_id) {
            $user = User::find($user_id);

            if (!$user)
                $logs[] = self::getFormattedDate() . ", execute: $workflowAction->action: unable to locate user #$user_id, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";

            if ($user->phone) {
                $phones[] = $user->phone;
            } else {
                $logs[] = self::getFormattedDate() . ", execute: $workflowAction->action: user #$user_id, does not have a phone to use, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
            }
        }


        //send message

        foreach ($phones as $phone) {
            if ((new InternationalPhoneRule(false))->passes("phone", $phone)) {
                $logs[] = self::getFormattedDate() . ", $workflowAction->action: sending sms to #$phone, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";

                $msg_content = $this->evaluateModelAttributeInString($workflowAction->sms_message);

                $sms = (new SMSService())
                    ->getProviderViaServiceClass($workflowAction->sms_provider_class)
                    ->sendTextMessage($phone, $msg_content, null, "other", 'workflow');

                if ($sms->error) {
                    $logs[] = self::getFormattedDate() . ", execute: $workflowAction->action: error sending sms to #$phone, error #$sms->error, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
                } else {
                    $logs[] = self::getFormattedDate() . ", execute: $workflowAction->action: sms sent (but not confirmed) to #$phone, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
                }

                $meta['sms_id'] = $sms->id;

            } else {
                $logs[] = self::getFormattedDate() . ", execute: $workflowAction->action: phone #$phone, is invalid, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $actionExecution->update(
            [
                'execution_time' => $executionTime,
                'logs' => $logs,
                'meta' => $meta,
            ]
        );
    }

    protected function notifyControlPanelUser(WorkflowAction $workflowAction)
    {
        $startTime = microtime(true);

        if ($workflowAction->action !== WorkflowAction::ACTION_NOTIFY_CONTROL_PANEL_USER)
            throw new \Exception("$workflowAction->action: Incompatible execution method.");


        $actionExecution = WorkflowActionExecution::create(
            [
                'tenant_id' => auth('sanctum')->id() ?? filament()->getTenant()->id,
                'workflow_action_id' => $workflowAction->id,
                'model_id' => $this->model_id,
                'execution_time' => null,
                'logs' => null,
                'meta' => null,
            ]
        );

        $logs = [];
        $meta = [];

        $notify_users_ids = $workflowAction->notify_control_panel_alert_recipients ?? [];

        $status = $workflowAction->notify_control_panel_alert_status;
        $title = $workflowAction->notify_control_panel_alert_title;
        $body = $workflowAction->notify_control_panel_alert_body;
        $broadcast = $workflowAction->notify_control_panel_broadcast;

        try {

            $logs[] = self::getFormattedDate() . ", execute: $workflowAction->action: sending alert to #users, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";

            $users = \App\Models\User::findMany($notify_users_ids);

            if ($users->isNotEmpty()) {
                fns()
                    ->title(Str::inlineMarkdown($this->evaluateModelAttributeInString($title)))
                    ->body(Str::inlineMarkdown($this->evaluateModelAttributeInString($body)))
                    ->status($status)
                    ->recipients($users)
                    ->broadcast($broadcast ? $users : collect([]))
                    ->send();
            }

            $logs[] = self::getFormattedDate() . ", execute: $workflowAction->action: alert sent to " . $users->count() . " #user(s), workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";

        } catch (\Exception $exception) {
            $logs[] = self::getFormattedDate() . ", execute: $workflowAction->action: error sending alert to #users, error #" . $exception->getMessage() . " , workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $actionExecution->update(
            [
                'execution_time' => $executionTime,
                'logs' => $logs,
                'meta' => $meta,
            ]
        );

    }


    public function pushNotification(WorkflowAction $workflowAction)
    {
        $startTime = microtime(true);

        if ($workflowAction->action !== WorkflowAction::ACTION_PUSH_NOTIFICATION)
            throw new \Exception("$workflowAction->action: Incompatible execution method.");


        $logs = [];
        $meta = [];

        $model = ($workflowAction->workflow->model_type)::with($workflowAction->notifiable_relations ?? [])->find($this->model_id);

        $actionExecution = WorkflowActionExecution::create(
            [
                'tenant_id' => auth('sanctum')->id() ?? filament()->getTenant()->id,
                'workflow_action_id' => $workflowAction->id,
                'model_id' => $this->model_id,
                'execution_time' => null,
                'logs' => null,
                'meta' => null,
            ]
        );

        $count_nf = count($workflowAction->notifiable_relations ?? []);

        $logs[] = self::getFormattedDate() . ", execute: $workflowAction->action: sending push notification to #notifiable($count_nf), workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";

        foreach ($workflowAction->notifiable_relations ?? [] as $relation) {
            if ($model->{$relation} instanceof Model) {
                if (ClassService::instance()->classUses(Notifiable::class, $model->{$relation})) {
                    $token = $model->{$relation}->{$workflowAction->notifiable_token_attribute_name};
                    $response = send_notification_FCM_via_tokens([$token], $workflowAction->notify_control_panel_alert_title, $workflowAction->notify_control_panel_alert_body, $this->buildPushNotificationData($workflowAction));

                    $response = json_decode($response);

                    $relation_id = $model->{$relation}->id;
                    if ($response and $response->success == 1) {
                        $logs[] = self::getFormattedDate() . ", push notification complete for $relation #$relation_id, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
                    } else {
                        $error = $response == null ? "Unknown error" : $response->results[0]->error;
                        $logs[] = self::getFormattedDate() . ", failed to push notification, error: $error, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
                    }
                }
            }
            if ($model->{$relation} instanceof Collection or $model->{$relation} instanceof \Illuminate\Database\Eloquent\Collection) {
                foreach ($model->{$relation} as $notifiable) {
                    if (ClassService::instance()->classUses(Notifiable::class, $notifiable)) {
                        $token = $model->{$relation}->{$workflowAction->notifiable_token_attribute_name};
                        $response = send_notification_FCM_via_tokens([$token], $workflowAction->notify_control_panel_alert_title, $workflowAction->notify_control_panel_alert_body, $this->buildPushNotificationData($workflowAction, true));

                        $response = json_decode($response);

                        $relation_id = $notifiable->id;
                        $relation_name = class_basename($notifiable);

                        if ($response and $response->success == 1) {
                            $logs[] = self::getFormattedDate() . ", push notification complete for $relation_name #$relation_id, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
                        } else {
                            $error = $response == null ? "Unknown error" : $response->results[0]->error;
                            $logs[] = self::getFormattedDate() . ", failed to push notification for $relation_name #$relation_id, error: $error, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
                        }
                    }
                }
            }
        }

        $notifiable_users = User::findMany($workflowAction->notifiable_users ?? []);

        foreach ($notifiable_users as $user) {
            $token = $user->{$workflowAction->notifiable_token_attribute_name};
            $response = send_notification_FCM_via_tokens([$token], $workflowAction->notify_control_panel_alert_title, $workflowAction->notify_control_panel_alert_body, $this->buildPushNotificationData($workflowAction));

            $response = json_decode($response);

            if ($response and $response->success == 1) {
                $logs[] = self::getFormattedDate() . ", push notification complete for user #$user->id, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
            } else {
                $error = $response == null ? "Unknown error" : $response->results[0]->error;
                $logs[] = self::getFormattedDate() . ", failed to push notification for user #$user->id, error: $error, workflow #" . $this->workflow->id . " on trigger #" . $this->workflow->model_type . " #$this->model_id";
            }
        }

        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $actionExecution->update(
            [
                'execution_time' => $executionTime,
                'logs' => $logs,
                'meta' => $meta,
            ]
        );


    }


    protected function evaluateModelAttributeInString(string $data): string
    {
        $model = ($this->workflow->model_type)::find($this->model_id);

        if ($model) {
            $segments = explode(' ', $data);
            foreach ($segments as $segment) {
                preg_match('~@(.*?)@~', $segment, $output);
                if (array_key_exists(1, $output)) {
                    //index 0 = @no@, index 1 = no
                    if (array_key_exists(0, $output) and array_key_exists(1, $output))
                        $data = str($data)->replace($output[0], $model->{$output[1]})->value();
                }
            }
        }
        return $data;
    }

    protected function logInWorkflow($log)
    {
        $this->workflow->refresh();
        $logs = $this->workflow->logs ?? [];
        $logs[] = $log;
        $this->workflow->update(['logs' => $logs]);
    }

    protected function getFormattedDate(): string
    {
        return now()->format('Y, j F, g:i a');
    }

    protected function buildPushNotificationData(WorkflowAction $workflowAction, $collection = false): array
    {
        $data = [
            'data' => [],
        ];
        //array of key, value, value_resource
        //passport, ['id' => 1, 'status' => 'pending'], (PassportResourceClass)
        $push_notification_include_data = $workflowAction->push_notification_include_data;

        $model = ($this->workflow->model_type)::find($this->model_id);

        foreach ($push_notification_include_data as $item) {

            $value = $item['value'];
            $valueResource = str($item['value_resource'])->replace('/', "\\")->value();

//        *This
            if ($value == $workflowAction->workflow->model_type) {
                $value = $model;
            } else {
                $value = $model->{$value}; //user, passports
            }

            if ($value instanceof Model) {
                $data['data'][$item['key']] = new ($valueResource)($value);
            } else {
                $data['data'][$item['key']] = ($valueResource)::collection($value);
            }
        }

        return $data;
    }
}
