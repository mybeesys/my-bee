<?php

namespace App\Filament\Admin\Resources\ClientResource\Pages;

use App\Filament\Admin\Resources\ClientResource;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Subscription;
use App\Rules\TenantEmailRule;
use App\Rules\TenantPhoneRule;
use App\Services\TenantService;
use Filament\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_activity')
                ->visible(fn(Client $client) => $client->tenants->isEmpty())
                ->color('success')
                ->label(__('fields.add_activity'))
                ->requiresConfirmation()
                ->modalWidth('4xl')
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->closeModalByClickingAway(false)
                ->form(function (Client $record) {
                    return [
                        Section::make(__('fields.company_or_individual_details'))->schema([
                            Select::make('type')
                                ->label(__('fields.type'))
                                ->reactive()
                                ->required()
                                ->options(
                                    [
                                        'company' => str(__('fields.company'))->replace('ال', '')->value(),
                                        'individual' => __('fields.individual')
                                    ]),

                            TextInput::make('name')
                                ->label(__('fields.name'))
                                ->required()
                                ->live(true)
                                ->afterStateUpdated(fn(Set $set, ?string $state) => $set('slug', custom_slug($state))),

                            TextInput::make('company_person')
                                ->label(__('fields.company_contact_person'))
                                ->visible(fn(Get $get) => $get('type') === 'company')
                                ->required(),

                            TextInput::make('trn')
                                ->label(__('fields.trn'))
                                ->visible(fn(Get $get) => $get('type') === 'company')
                                ->required(),

                            TextInput::make('phone')
                                ->label(__('fields.phone'))
                                ->tel()
                                ->required()
                                ->rules([new TenantPhoneRule($record)]),

                            TextInput::make('mobile')
                                ->label(__('fields.mobile'))
                                ->tel()
                                ->required(),

                            TextInput::make('email')
                                ->label(__('fields.email'))
                                ->email()
                                ->required()
                                ->rules([new TenantEmailRule($record)]),

                            TextInput::make('address')
                                ->label(__('fields.address')),

                            TextInput::make('slug')
                                ->label(__('fields.slug'))
                                ->required()
                                ->unique(table: 'tenants')
                                ->dehydrateStateUsing(fn(string $state) => custom_slug($state))
                                ->reactive()
                                ->helperText(function ($state) {
                                    if ($state)
                                        return env('CLIENT_URL') . custom_slug($state);
                                    return env('CLIENT_URL') . 'your-activity';
                                }),

                        ])->columns(3),

                    ];
                })
                ->action(function (array $data, Client $record, Actions\Action $action) {
                    try {
                        DB::beginTransaction();

                        TenantService::instance()->createTenant($record, $data);

                        DB::commit();

                        fns()->saved();

                    } catch (\Throwable $exception) {
                        DB::rollBack();
                        fns()->sendDanger("تعذر إضافة النشاط", 'حدث خطأ أثناء إنشاء النشاط.');
                        dd($exception);
                        $action->halt();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['plan_id'] = $this->record->subscription->plan_id;

        return parent::mutateFormDataBeforeFill($data); // TODO: Change the autogenerated stub
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $model = null;
        try {
            DB::beginTransaction();

            if ($record->subscription->plan->id != $data['plan_id']){
                Subscription::subscribe(Plan::findOrFail($data['plan_id']), $this->record);
            }

            $model = parent::handleRecordUpdate($record, Arr::except($data, [
                'plan_id',
                'user_first_name',
                'user_second_name',
                'user_third_name',
                'user_fourth_name',
                'user_phone',
                'user_email',
                'user_password',
                'user_password_confirmation',

            ]));

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            fns()->displayException($e);
            $this->halt();
        }
        return $model;
    }
}
