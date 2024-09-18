<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Resources\WorkflowResource\Pages;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowAction;
use App\Services\ClassService;
use App\Services\RoleService;
use App\Services\SMSService;
use App\Services\WorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $slug = "settings/workflows";

    protected static ?string $recordTitleAttribute = "description";

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Description')
                    ->collapsed(fn($context) => $context === "edit" or $context === "view")
                    ->description("Briefly describe the workflow")
                    ->collapsible()
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->placeholder('e.g. notify supervisors after a user of role x created')
                            ->required(),
                    ]),
                Forms\Components\Section::make("Trigger and conditions")
                    ->collapsed(fn($context) => $context === "edit" or $context === "view")
                    ->description("Specify what triggers this workflow")
                    ->collapsible()
                    ->schema([
                        Forms\Components\Radio::make('trigger')
                            ->required()
                            ->live()
                            ->disableOptionWhen(fn($value) => $value === "custom")
                            ->default('model')
                            ->descriptions([
                                'model' => 'A record represents an item in the system, like a user for example',
                                'custom' => 'Custom triggers are not available right now.'
                            ])
                            ->options([
                                'model' => 'Record',
                                'custom' => 'Custom',
                            ]),


                        Forms\Components\Fieldset::make("Record")
                            ->visible(fn(Forms\Get $get) => $get('trigger') === "model")
                            ->schema([

                                Forms\Components\Hidden::make('has_roles')->dehydrated(false)->default(false),

                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\Select::make('model_type')
                                            ->label("Record type")
                                            ->live()
                                            ->required()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if ($state) {
                                                    $usesHasRoles = (new WorkflowService())->modelHasRoles($state);
                                                    if ($usesHasRoles) {
                                                        $set('has_roles', true);
                                                    } else {
                                                        $set('has_roles', false);
                                                        $set('role_usage', 'not-supported');
                                                        $set('roles_names', []);
                                                    }
                                                }
                                            })
                                            ->options(function () {
                                                return (new WorkflowService())->listTriggers();
                                            }),

                                        Forms\Components\Select::make('role_usage')
                                            ->visible(fn(Forms\Get $get) => $get('has_roles') === true)
                                            ->live()
                                            ->required()
                                            ->options([
                                                'any-role' => 'Any role',
                                                'specified' => 'Specified role',
                                            ]),

                                        Forms\Components\Select::make('roles_names')
                                            ->label('Roles')
                                            ->visible(fn(Forms\Get $get) => $get('has_roles') === true and $get('role_usage') === "specified")
                                            ->live()
                                            ->required()
                                            ->multiple()
                                            ->options(function () {
                                                return (new WorkflowService())->listRoles();
                                            }),

                                        Forms\Components\Select::make('model_event')
                                            ->label("On")
                                            ->required()
                                            ->live()
                                            ->options([
                                                'created' => 'New record',
                                                'updated' => 'Record updated',
                                                'deleted' => 'Record deleted',
                                            ]),

                                        Forms\Components\Hidden::make('model_comparison')->default('any-attribute'),

                                        Forms\Components\Select::make('model_attribute')
                                            ->visible(fn(Forms\Get $get) => $get('model_event') === "updated")
                                            ->label("Updated attribute")
                                            ->default('any-attribute')
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if ($state and $state === "any-attribute") {
                                                    $set('model_comparison', 'any-attribute');
                                                }

                                                if ($state and $state !== "any-attribute") {
                                                    $set('model_comparison', "specified");
                                                }
                                            })
                                            ->options(function (Forms\Get $get) {
                                                $model_class = $get('model_type');
                                                if ($model_class) {
                                                    return array_merge(
                                                        [
                                                            null => '* Any attribute'
                                                        ],
                                                        (new WorkflowService())->getTriggerAttributes($model_class, true, true)
                                                    );
                                                }
                                            }),

                                        Forms\Components\Select::make('condition_type')
                                            ->label("Condition")
                                            ->live()
                                            ->required()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if ($state === "no-condition-is-required") {
                                                    $set('conditions', []);
                                                }
                                            })
                                            ->options([
                                                'no-condition-is-required' => 'No condition required',
                                                'all-conditions-are-true' => 'All conditions are true',
                                                'any-condition-is-true' => 'Any condition is true'
                                            ]),
                                    ]),
                                Forms\Components\Repeater::make('conditions')
                                    ->relationship('conditions')
                                    ->visible(fn(Forms\Get $get) => $get('condition_type') === "all-conditions-are-true" or $get('condition_type') === "any-condition-is-true")
                                    ->schema([
                                        Forms\Components\Select::make('model_attribute')
                                            ->label('Record attribute')
                                            ->live()
                                            ->required()
                                            ->options(function (Forms\Get $get) {
                                                $model_class = $get('../../model_type');
                                                if ($model_class) {
                                                    return (new WorkflowService())->getTriggerAttributes($model_class, true, true);
                                                }
                                                return [];
                                            }),
                                        Forms\Components\Select::make('operator')
                                            ->required()
                                            ->options([
                                                'is-equal-to' => 'Is equal to',
                                                'is-not-equal-to' => 'Is not equal to',
                                                'equals-or-greater-than' => 'Equals or greater than',
                                                'equals-or-less-than' => 'Equals or less than',
                                                'greater-than' => 'Greater than',
                                                'less-than' => 'Less than',
                                            ]),
                                        Forms\Components\TextInput::make('compare_value')
                                            ->required()
                                            ->hint(function (Forms\Get $get) {
                                                $model_class = $get('../../model_type');
                                                $model_attribute = $get('model_attribute');
                                                if ($model_class and $model_attribute) {
                                                    return (new WorkflowService())->getTableColumnType($model_class, $model_attribute);
                                                }
                                            }),

                                    ])->columnSpan(2)->columns(3),
                            ]),
                    ]),

                Forms\Components\Section::make("Actions")
                    ->collapsed(fn($context) => $context === "edit" or $context === "view")
                    ->description("Specify what happens after all of your pre-conditions were met")
                    ->collapsible()
                    ->schema([
                        Forms\Components\Repeater::make('actions')
                            ->relationship('actions')
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data) {
                                if ($data['action'] === WorkflowAction::ACTION_NOTIFY_CONTROL_PANEL_USER or $data['action'] === WorkflowAction::ACTION_SEND_SMS) {
                                    $data['recipients_selection'] = "roles";
                                }
                                return self::mutateWorkflowActionsDataBeforeFill($data);
                            })
                            ->schema([

                                hidden_tenant_id_field(),

                                Forms\Components\Select::make('action')
                                    ->required()
                                    ->live()
                                    ->options(function () {
                                        return (new WorkflowService())->getAvailableActions();
                                    }),

                                Forms\Components\Select::make('sms_provider_class')
                                    ->label("Sms provider")
                                    ->visible(fn(Forms\Get $get) => $get('action') === "send-sms")
                                    ->required()
                                    ->options(function () {
                                        return (new SMSService())->listSmsProviders();
                                    }),

                                Forms\Components\Select::make('send_sms_to')
                                    ->dehydrated(false)
                                    ->visible(fn(Forms\Get $get) => $get('action') === "send-sms")
                                    ->required()
                                    ->live()
                                    ->options([
                                        'users' => 'Users',
                                        'plain-phone' => 'Manually add phone(s)',
                                    ]),

                                Forms\Components\Select::make('recipients_selection')
                                    ->dehydrated(false)
                                    ->visible(fn(Forms\Get $get) => ($get('action') === "send-sms" and $get('send_sms_to') === "users") or $get('action') === "notify-control-panel-user")
                                    ->live()
                                    ->required()
                                    ->options([
                                        'roles' => 'Via roles',
                                        'record-related-recipients' => 'Record related recipients',
                                    ]),


                                Forms\Components\Select::make('notifiable_users')
                                    ->visible(fn(Forms\Get $get) => ($get('action') === "send-sms" and $get('send_sms_to') === "record-related-recipients") or ($get('recipients_selection') === 'record-related-recipients' and $get('action') === "notify-control-panel-user") or $get('action') === "push-notification")
                                    ->multiple()
                                    ->options(function () {
                                        $users = User::with('roles')->get();
                                        $data = [];
                                        foreach ($users as $user) {
                                            $data[$user->id] = $user->full_name . " ({$user->role()})";
                                        }
                                        return $data;
                                    }),

                                Forms\Components\Select::make('notifiable_relations')
                                    ->visible(fn(Forms\Get $get) => ($get('action') === "send-sms" and $get('send_sms_to') === "record-related-recipients") or ($get('recipients_selection') === 'record-related-recipients' and $get('action') === "notify-control-panel-user") or $get('action') === "push-notification")
                                    ->live()
                                    ->multiple()
                                    ->options(function (Forms\Get $get) {
                                        $model_class = $get('../../model_type');
                                        if ($model_class) {
                                            return (new WorkflowService())->getModelRelationshipMethods($model_class, restrictToOnlyNotifiable: true);
                                        }
                                        return [];
                                    }),

                                Forms\Components\TextInput::make('notifiable_token_attribute_name')
                                    ->required()
                                    ->placeholder("fcm_token")
                                    ->visible(fn(Forms\Get $get) => $get('action') === "push-notification"),

                                Forms\Components\Select::make('action_user_roles')
                                    ->dehydrated(false)
                                    ->label('Roles')
                                    ->visible(fn(Forms\Get $get) => ($get('action') === "send-sms" and $get('send_sms_to') === "users") or ($get('recipients_selection') === 'roles' and $get('action') === "notify-control-panel-user"))
                                    ->live()
                                    ->required()
                                    ->multiple()
                                    ->options(function () {
                                        return (new WorkflowService())->listRoles();
                                    }),

                                Forms\Components\Select::make('sms_recipients')
                                    ->visible(fn(Forms\Get $get) => $get('recipients_selection') === 'roles' and $get('action') === "send-sms" and $get('send_sms_to') === "users")
                                    ->required()
                                    ->multiple()
                                    ->options(function (Forms\Get $get) {
                                        $roles = $get('action_user_roles');
                                        return User::whereHas('roles', function ($q) use ($roles) {
                                            return $q->whereIn('name', $roles ?? []);
                                        })->get()->pluck('full_name', 'id');
                                    }),

                                Forms\Components\Textarea::make('sms_message')
                                    ->visible(fn(Forms\Get $get) => $get('action') === "send-sms")
                                    ->required()
                                    ->helperText('Supports record attributes e.g. @full_name@')
                                    ->columnSpan(3),

                                Forms\Components\Repeater::make('sms_recipients_phones')
                                    ->visible(fn(Forms\Get $get) => $get('action') === "send-sms" and $get('send_sms_to') === "plain-phone")
                                    ->schema([
                                        Forms\Components\TextInput::make('phone')
                                            ->required()
                                            ->hint('International numbers only e.g. 2499********')
                                            ->numeric()
                                            ->tel(),
                                    ])->columnSpan(2),

                                Forms\Components\Select::make('notify_control_panel_alert_recipients')
                                    ->visible(fn(Forms\Get $get) => $get('recipients_selection') === 'roles' and $get('action') === "notify-control-panel-user")
                                    ->label('Notification recipients')
                                    ->required()
                                    ->multiple()
                                    ->options(function (Forms\Get $get) {
                                        $roles = $get('action_user_roles');

                                        return User::whereHas('roles', function ($q) use ($roles) {
                                            return $q->whereIn('name', $roles ?? []);
                                        })->get()->pluck('full_name', 'id');

                                    }),

                                Forms\Components\Select::make('notify_control_panel_alert_status')
                                    ->visible(fn(Forms\Get $get) => $get('action') === "notify-control-panel-user")
                                    ->label('Notification icon')
                                    ->required()
                                    ->options([
                                        'success' => 'Success',
                                        'warning' => 'Warning',
                                        'danger' => 'danger',
                                    ]),

                                Forms\Components\TextInput::make('notify_control_panel_alert_title')
                                    ->visible(fn(Forms\Get $get) => $get('action') === "notify-control-panel-user" or $get('action') === "push-notification")
                                    ->label('Notification title')
                                    ->helperText('Supports record attributes e.g. @full_name@')
                                    ->required(),

                                Forms\Components\MarkdownEditor::make('notify_control_panel_alert_body')
                                    ->visible(fn(Forms\Get $get) => $get('action') === "notify-control-panel-user" or $get('action') === "push-notification")
                                    ->label('Notification body')
                                    ->columnSpan(3)
                                    ->helperText(function (Forms\Get $get) {
                                        $model_class = $get('../../model_type');
                                        if ($model_class) {
                                            $relations = (new WorkflowService())->getModelRelationshipMethods($model_class);
                                            return "Supports record attributes e.g. @full_name@, available relations, " . implode(', ', $relations);
                                        }
                                        return "Supports record attributes e.g. @full_name@";

                                    })
                                    ->required(),

                                Forms\Components\Repeater::make("push_notification_include_data")
                                    ->visible(fn(Forms\Get $get) => $get('action') === "push-notification")
                                    ->schema([

                                        Forms\Components\TextInput::make('key')
                                            ->required()
                                            ->minLength(1)
                                            ->maxLength(255),

                                        Forms\Components\Select::make('value')
                                            ->required()
                                            ->options(function (Forms\Get $get) {
                                                $model_class = $get('data.model_type', true);
                                                if ($model_class) {
                                                    $short_name = class_basename($model_class);
                                                    $data = [$model_class => "*This ($short_name)"];
                                                    $items = array_merge($data, (new WorkflowService())->getModelRelationshipMethods($model_class));
                                                    return $items;
                                                }
                                                return [];
                                            }),

                                        Forms\Components\Select::make('value_resource')
                                            ->required()
                                            ->options(ClassService::instance()->listHttpResources()),

                                    ])->columns(3)->columnSpanFull(),

                                Forms\Components\Checkbox::make("notify_control_panel_broadcast")
                                    ->visible(fn(Forms\Get $get) => $get('action') === "notify-control-panel-user")
                                    ->label('Broadcast (send realtime notification)')
                                    ->default(true),

                            ])->minItems(1)->reorderable()->collapsible()->columns(3),
                    ]),

                Forms\Components\Section::make('Active')
                    ->description("Enable or disable workflow")
                    ->collapsed(fn($context) => $context === "edit" or $context === "view")
                    ->schema([
                        Forms\Components\Checkbox::make('active'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->prefix('#'),
                Tables\Columns\TextColumn::make('description')
                    ->description(fn($record) => $record->statement)
                    ->tooltip(fn($record) => $record->statement)
                    ->limit(60),

                Tables\Columns\TextColumn::make('actions')
                    ->tooltip(fn($record) => $record->actions_statement)
                    ->getStateUsing(fn($record) => $record->actions_statement),

                Tables\Columns\TextColumn::make('executions_count')->counts('executions'),
                Tables\Columns\IconColumn::make('active')->boolean(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()->action(function (Workflow $record) {
                        try {
                            \DB::beginTransaction();
                            $record->executions()->delete();
                            $record->actions()->delete();
                            $record->conditions()->delete();
                            $record->delete();
                            \DB::commit();
                            fns()->title("Deleted")->send();

                        } catch (\Exception $exception) {
                            \DB::rollBack();
                            report($exception);
                            fns()->displayException($exception);
                        }
                    })
                ])
            ])
            ->bulkActions([
            ])->deferLoading();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['conditions', 'actions.executions', 'executions'])->latest();
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\WorkflowResource\Pages\ListWorkflows::route('/'),
            'create' => \App\Filament\Tenant\Resources\WorkflowResource\Pages\CreateWorkflow::route('/create'),
            'edit' => \App\Filament\Tenant\Resources\WorkflowResource\Pages\EditWorkflow::route('/{record}/edit'),
            'view' => \App\Filament\Tenant\Resources\WorkflowResource\Pages\ViewWorkflow::route('/{record}'),
            'viewLogs' => \App\Filament\Tenant\Resources\WorkflowResource\Pages\ViewLogs::route('/{record}/view-logs'),
        ];
    }

    protected static function mutateWorkflowActionsDataBeforeFill(array $data): array
    {
        $action = $data['action'];

        switch ($action) {
            case WorkflowAction::ACTION_SEND_SMS:
            {
                if ($data['sms_recipients_phones']) {
                    $data['send_sms_to'] = 'plain-phone';
                }
                if ($data['sms_recipients']) {
                    $roles = (new RoleService())->getRoles($data['sms_recipients']);
                    $data['send_sms_to'] = 'users';
                    $data['action_user_roles'] = $roles;
                }
                break;
            }
            case WorkflowAction::ACTION_NOTIFY_CONTROL_PANEL_USER:
            {
                $roles = (new RoleService())->getRoles($data['notify_control_panel_alert_recipients']);
                $data['action_user_roles'] = $roles;
                break;
            }
            case WorkflowAction::ACTION_PUSH_NOTIFICATION:
            {
                break;
            }
            default:
            {
                dd('unsupported action: ' . $action);
            }
        }
        return $data;
    }

}
