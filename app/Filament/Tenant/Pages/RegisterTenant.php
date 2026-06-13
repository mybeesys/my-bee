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
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;


class RegisterTenant extends BaseRegisterTenant
{
    protected static ?string $slug = "new-activity";

    protected static string $view = 'filament.tenant.pages.register-tenant';

    protected ?string $maxWidth = '4xl';


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

    public function hasReachedActivitiesLimit(): bool
    {
        $client = tenant_client();

        return $client->tenants->count() >= $client->subscription->plan->max_allowed_companies;
    }

    public function form(Form $form): Form
    {
        $client = tenant_client();

        if ($this->hasReachedActivitiesLimit()) {
            return $form->schema([]);
        }

        $clientUrl = rtrim((string) env('CLIENT_URL', ''), '/');

        return $form
            ->schema([
                Section::make(__('fields.company_or_individual_details'))
                    ->description(__('fields.register_activity_identity_hint'))
                    ->icon('heroicon-o-building-office-2')
                    ->extraAttributes(['class' => 'register-activity-section'])
                    ->schema([
                        Select::make('type')
                            ->label(__('fields.type'))
                            ->live()
                            ->required()
                            ->columnSpanFull()
                            ->options([
                                'company' => str(__('fields.company'))->replace('ال', '')->value(),
                                'individual' => __('fields.individual'),
                            ]),

                        TextInput::make('name')
                            ->label(__('fields.name'))
                            ->required()
                            ->live(true)
                            ->unique()
                            ->columnSpan(['default' => 2, 'md' => 1])
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', custom_slug($state))),

                        TextInput::make('company_person')
                            ->label(__('fields.company_contact_person'))
                            ->visible(fn (Get $get) => $get('type') === 'company')
                            ->required()
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('trn')
                            ->label(__('fields.trn'))
                            ->visible(fn (Get $get) => $get('type') === 'company')
                            ->unique(table: Tenant::class)
                            ->required()
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('slug')
                            ->label(__('fields.slug'))
                            ->required()
                            ->unique(table: 'tenants', ignorable: fn (?Model $record): ?Model => $record)
                            ->dehydrateStateUsing(fn (string $state) => custom_slug($state))
                            ->live()
                            ->columnSpanFull()
                            ->helperText(function (?string $state) use ($clientUrl) {
                                $slug = filled($state) ? custom_slug($state) : 'your-activity';
                                $url = $clientUrl . '/' . $slug;

                                return new HtmlString(
                                    '<span class="register-activity-url-hint">' . e($url) . '</span>'
                                );
                            }),
                    ])
                    ->columns(2),

                Section::make(__('fields.activity_contact_details'))
                    ->description(__('fields.register_activity_contact_hint'))
                    ->icon('heroicon-o-phone')
                    ->extraAttributes(['class' => 'register-activity-section'])
                    ->schema([
                        TextInput::make('phone')
                            ->label(__('fields.phone'))
                            ->tel()
                            ->required()
                            ->default($client->phone)
                            ->rules([new TenantPhoneRule()])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('mobile')
                            ->label(__('fields.mobile'))
                            ->tel()
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('email')
                            ->label(__('fields.email'))
                            ->email()
                            ->required()
                            ->default($client->email)
                            ->rules([new TenantEmailRule()])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('address')
                            ->label(__('fields.address'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                View::make('components.loading'),
            ])
            ->statePath('data');
    }

    protected function handleRegistration(array $data): Tenant
    {
        try {
            DB::beginTransaction();

            $tenantData = $data['data'] ?? $data;

            $tenant = TenantService::instance()->createTenant(tenant_client(), $tenantData);

            DB::commit();

        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            fns()->sendDanger("تعذر إضافة النشاط", 'حدث خطأ أثناء إنشاء النشاط.');
            $this->halt();
        }
        return $tenant;
    }


    protected function getFormActions(): array
    {
        if ($this->hasReachedActivitiesLimit()) {
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
