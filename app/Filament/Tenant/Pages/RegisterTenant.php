<?php

namespace App\Filament\Tenant\Pages;


use App\Models\Tenant;
use App\Models\User;
use App\Rules\TenantEmailRule;
use App\Rules\TenantPhoneRule;
use App\Rules\UniqueTenantItemRule;
use App\Services\TenantService;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;


class RegisterTenant extends BaseRegisterTenant
{

    protected static ?string $slug = "new-activity";


    public ?array $data = [];


    public function getTitle(): string|Htmlable
    {
        $client = tenant_client();

        $activitiesLimitReached = $client->tenants->count() >= $client->subscription->plan->max_allowed_companies;

        if ($activitiesLimitReached)
            return __('messages.activities_limit_reached');

        return __('messages.greeting') . ", " . filament()->auth()->user()->full_name;
    }

    public static function getLabel(): string
    {
        if (tenant_client()->tenants->isEmpty())
            return __('messages.complete_registration');

        return __('messages.add_new_activity');

    }

    public function getSubheading(): string|Htmlable|null
    {
        $client = tenant_client();

        $activitiesLimitReached = $client->tenants->count() >= $client->subscription->plan->max_allowed_companies;

        if ($activitiesLimitReached)
            return __('messages.activities_limit_reached_upgrade_plan_or_contact_support');

        return __('messages.complete_registration_to_continue');
    }


    public function form(Form $form): Form
    {

        // allow companies from same client to use the same email or phone

        $client = tenant_client();

        $activitiesLimitReached = $client->tenants->count() >= $client->subscription->plan->max_allowed_companies;

        if ($activitiesLimitReached) {
            $schema = [];
        } else {
            $schema = [
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
                        ->unique()
                        ->afterStateUpdated(fn(Set $set, ?string $state) => $set('slug', custom_slug($state))),


                    TextInput::make('company_person')
                        ->label(__('fields.company_contact_person'))
                        ->visible(fn(Get $get) => $get('type') === 'company')
                        ->required(),

                    TextInput::make('trn')
                        ->label(__('fields.trn'))
                        ->visible(fn(Get $get) => $get('type') === 'company')
                        ->unique(table: Tenant::class)
                        ->required(),

                    TextInput::make('phone')
                        ->label(__('fields.phone'))
                        ->tel()
                        ->required()
                        ->default($client->phone)
                        ->rules([new TenantPhoneRule()]),

                    TextInput::make('mobile')
                        ->label(__('fields.mobile'))
                        ->tel(),

                    TextInput::make('email')
                        ->label(__('fields.email'))
                        ->email()
                        ->required()
                        ->default($client->email)
                        ->rules([new TenantEmailRule()]),

                    TextInput::make('address')
                        ->label(__('fields.address')),

                    TextInput::make('slug')
                        ->label(__('fields.slug'))
                        ->required()
                        ->unique(table: 'tenants', ignorable: fn(?Model $record): ?Model => $record)
                        ->dehydrateStateUsing(fn(string $state) => custom_slug($state))
                        ->reactive()
                        ->helperText(function ($state){
                            if($state)
                                return env('CLIENT_URL') . custom_slug($state);
                            return env('CLIENT_URL') . 'your-activity';
                        }),
                ])->statePath('data'),
            ];
        }

        return $form
            ->schema($schema);
    }

    protected function handleRegistration(array $data): Tenant
    {
        try {
            DB::beginTransaction();

            $tenant = TenantService::instance()->createTenant(tenant_client(), $data['data']);

            DB::commit();

        } catch (\Throwable $exception) {
            DB::rollBack();
            fns()->sendDanger("تعذر إضافة النشاط", 'حدث خطأ أثناء إنشاء النشاط.');
            dd($exception);
            $this->halt();
        }
        return $tenant;
    }


    protected function getFormActions(): array
    {
        $client = filament()->auth()->user()->client;

        $activitiesLimitReached = $client->tenants->count() >= $client->subscription->plan->max_allowed_companies;

        if ($activitiesLimitReached) {
            return [
                Action::make('back')
                    ->label(__('messages.ok'))
                    ->url(url()->previous())
            ];
        }

        return parent::getFormActions(); // TODO: Change the autogenerated stub
    }

    public static function canView(): bool
    {
        return filament()->auth()->user()->hasRole(User::ROLE_CLIENT);
//
////        Gate::forUser($user)->authorize($action, $model);
//
//        try {
//            return authorize('create', Filament::getTenantModel())->allowed();
//        } catch (AuthorizationException $exception) {
//            return $exception->toResponse()->allowed();
//        }
    }
}
