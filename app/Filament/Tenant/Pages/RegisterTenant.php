<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\InternationalPhoneRule;
use App\Rules\UniqueClientAttributeRule;
use App\Rules\UniqueTenantNameRule;
use App\Services\RoleService;
use App\Services\TenantNamingService;
use App\Services\TenantService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Http\Middleware\Authenticate;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Filament\Support\is_app_url;

class RegisterTenant extends BaseRegisterTenant
{
    use WithRateLimiting;

    protected static ?string $slug = 'new-activity';

    protected static string $view = 'filament.tenant.pages.register-tenant';

    protected static string | array $withoutRouteMiddleware = [
        Authenticate::class,
    ];

    protected ?string $maxWidth = '4xl';

    public ?array $data = [];

    public function mount(): void
    {
        if (Filament::auth()->check() && ! static::canView()) {
            abort(404);
        }

        if (! Filament::auth()->check() && ! registration_plan_selection()) {
            $redirectUrl = ChooseRegistrationPlan::getUrl();

            $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode() && is_app_url($redirectUrl));

            return;
        }

        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        if (Filament::auth()->check() && $this->hasReachedActivitiesLimit()) {
            return __('messages.activities_limit_reached');
        }

        if (! Filament::auth()->check()) {
            return __('fields.join_activity_title');
        }

        return __('messages.greeting') . ', ' . filament()->auth()->user()->full_name;
    }

    public static function getLabel(): string
    {
        if (! Filament::auth()->check()) {
            return __('fields.join_activity_submit');
        }

        if (tenant_client()->tenants->isEmpty()) {
            return __('messages.complete_registration');
        }

        return __('messages.add_new_activity');
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (Filament::auth()->check() && $this->hasReachedActivitiesLimit()) {
            return __('messages.activities_limit_reached_upgrade_plan_or_contact_support');
        }

        if (! Filament::auth()->check()) {
            return __('fields.join_activity_subheading');
        }

        return __('messages.complete_registration_to_continue');
    }

    public function hasReachedActivitiesLimit(): bool
    {
        if (! Filament::auth()->check()) {
            return false;
        }

        return companies_maxed_out();
    }

    public function form(Form $form): Form
    {
        if (Filament::auth()->check() && $this->hasReachedActivitiesLimit()) {
            return $form->schema([]);
        }

        $clientUrl = rtrim((string) env('CLIENT_URL', ''), '/');

        return $form
            ->schema([
                Section::make(__('fields.register_owner_account_details'))
                    ->description(__('fields.join_activity_account_hint'))
                    ->icon('heroicon-o-user-circle')
                    ->visible(fn (): bool => ! Filament::auth()->check())
                    ->extraAttributes(['class' => 'register-activity-section'])
                    ->schema([
                        TextInput::make('full_name')
                            ->label(__('fields.register_owner_name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, Get $get, TextInput $component): void {
                                if ($get('type') === 'company' && filled($state)) {
                                    $set('company_person', $state);
                                }

                                $this->afterStateValidate()($component);
                            })
                            ->columnSpanFull(),

                        TextInput::make('user_phone')
                            ->label(__('fields.phone'))
                            ->tel()
                            ->required()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Set $set, ?string $state, TextInput $component): void {
                                if (filled($state)) {
                                    $set('phone', $state);
                                }

                                $this->validateFormField($component->getStatePath());
                            })
                            ->rules([new InternationalPhoneRule(false), new UniqueClientAttributeRule('phone', 'phone')])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('user_email')
                            ->label(__('fields.email'))
                            ->email()
                            ->required()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Set $set, ?string $state, TextInput $component): void {
                                if (filled($state)) {
                                    $set('email', $state);
                                }

                                $this->validateFormField($component->getStatePath());
                            })
                            ->rules(['email', new UniqueClientAttributeRule('email', 'email')])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('password')
                            ->label(__('fields.password'))
                            ->password()
                            ->required()
                            ->rule(Password::default())
                            ->same('passwordConfirmation')
                            ->revealable()
                            ->live(debounce: 500)
                            ->afterStateUpdated($this->afterStateValidate(['data.passwordConfirmation']))
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('passwordConfirmation')
                            ->label(__('filament-panels::pages/auth/register.form.password_confirmation.label'))
                            ->password()
                            ->required()
                            ->same('password')
                            ->revealable()
                            ->dehydrated(false)
                            ->live(debounce: 500)
                            ->afterStateUpdated($this->afterStateValidate(['data.password']))
                            ->columnSpan(['default' => 2, 'md' => 1]),
                    ])
                    ->columns(2),

                Section::make(__('fields.register_project_details'))
                    ->description(__('fields.register_project_identity_hint'))
                    ->icon('heroicon-o-building-office-2')
                    ->extraAttributes(['class' => 'register-activity-section'])
                    ->schema([
                        Select::make('type')
                            ->label(__('fields.type'))
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set, ?string $state, Get $get, Component $component): void {
                                if ($state === 'company' && filled($get('full_name'))) {
                                    $set('company_person', $get('full_name'));
                                } elseif ($state === 'company' && Filament::auth()->check()) {
                                    $set('company_person', filament()->auth()->user()->full_name);
                                }

                                $this->afterStateValidate()($component);
                            })
                            ->columnSpan(['default' => 2, 'md' => 1])
                            ->options([
                                'company' => __('fields.register_type_project'),
                                'individual' => __('fields.register_type_individual'),
                            ]),

                        TextInput::make('name')
                            ->label(__('fields.register_project_name'))
                            ->required()
                            ->live(debounce: 500)
                            ->rules([new UniqueTenantNameRule()])
                            ->afterStateUpdated($this->afterStateValidate())
                            ->columnSpan(['default' => 2, 'md' => 1])
                            ->helperText(function (?string $state) use ($clientUrl) {
                                if (blank($state)) {
                                    return null;
                                }

                                $naming = TenantNamingService::instance();
                                $slug = $naming->uniqueSlug($state);
                                $url = $clientUrl . '/' . $slug;
                                $parts = [
                                    '<span class="register-activity-url-hint">' . e(__('fields.register_project_url_preview', ['url' => $url])) . '</span>',
                                ];

                                if ($naming->nameExists($state)) {
                                    $suggestion = $naming->suggestUniqueName($state);
                                    $parts[] = '<span class="register-activity-name-hint">' . e(__('fields.register_project_name_suggestion', ['suggestion' => $suggestion])) . '</span>';
                                }

                                return new HtmlString(implode('<br>', $parts));
                            }),

                        TextInput::make('trn')
                            ->label(__('fields.trn'))
                            ->visible(fn (Get $get) => $get('type') === 'company')
                            ->unique(table: Tenant::class)
                            ->live(debounce: 500)
                            ->afterStateUpdated($this->afterStateValidate())
                            ->columnSpan(['default' => 2, 'md' => 1]),
                    ])
                    ->columns(2),

                View::make('components.loading'),
            ])
            ->statePath('data');
    }

    public function register(): void
    {
        if (! Filament::auth()->check()) {
            $this->registerAsGuest();

            return;
        }

        abort_unless(static::canView(), 404);

        parent::register();
    }

    protected function registerAsGuest(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            fns()->sendWarning(__('filament-panels::pages/auth/register.notifications.throttled.title', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => ceil($exception->secondsUntilAvailable / 60),
            ]));

            return;
        }

        try {
            $this->beginDatabaseTransaction();

            $data = $this->form->getState();

            $names = explode(' ', $data['full_name']);

            $user = User::query()->create([
                'first_name' => $names[0],
                'second_name' => $names[1] ?? '',
                'third_name' => $names[2] ?? null,
                'fourth_name' => $names[3] ?? null,
                'phone' => $data['user_phone'],
                'email' => $data['user_email'],
                'password' => Hash::make($data['password']),
            ]);

            (new RoleService())->assignRole($user, User::ROLE_CLIENT);

            $client = Client::query()->create([
                'name' => $data['full_name'],
                'phone' => $data['user_phone'],
                'email' => $data['user_email'],
                'user_id' => $user->id,
            ]);

            $tenantData = $this->prepareTenantRegistrationData($data);

            $this->tenant = TenantService::instance()->createTenant($client, $tenantData);

            Filament::auth()->login($user);
            session()->regenerate();

            $this->commitDatabaseTransaction();
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            return;
        } catch (\Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            report($exception);

            fns()->sendDanger(__('fields.join_activity_failed_title'), __('fields.join_activity_failed_body'));

            return;
        }

        $this->applyRegistrationPlanSubscription($client);

        $redirectUrl = Filament::getUrl($this->tenant);

        $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode() && is_app_url($redirectUrl));
    }

    protected function applyRegistrationPlanSubscription(Client $client): void
    {
        $selection = registration_plan_selection();

        if ($selection) {
            $plan = Plan::query()->find($selection['plan_id']);

            if ($plan) {
                Subscription::subscribe($plan, $client, $selection['billing_period']);
            }

            clear_registration_plan_selection();

            return;
        }

        $freePlan = Plan::query()
            ->where('code', Plan::CODE_FREE)
            ->where('active', true)
            ->first()
            ?? Plan::query()->where('active', true)->where('price', 0)->first();

        if ($freePlan) {
            Subscription::subscribe($freePlan, $client);
        }
    }

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        return $this->prepareTenantRegistrationData($data);
    }

    protected function prepareTenantRegistrationData(array $data): array
    {
        $client = Filament::auth()->check() ? tenant_client() : null;

        $data['slug'] = TenantNamingService::instance()->uniqueSlug($data['name']);
        $data['phone'] = $data['phone'] ?? $client?->phone ?? $data['user_phone'] ?? '';
        $data['email'] = $data['email'] ?? $client?->email ?? $data['user_email'] ?? '';
        $data['mobile'] = $data['mobile'] ?? $client?->mobile ?? null;
        $data['address'] = $data['address'] ?? null;

        if (($data['type'] ?? null) === 'company') {
            $data['company_person'] = $data['full_name']
                ?? filament()->auth()->user()?->full_name
                ?? $data['company_person']
                ?? '';
        }

        return Arr::except($data, [
            'full_name',
            'user_phone',
            'user_email',
            'password',
            'passwordConfirmation',
        ]);
    }

    protected function handleRegistration(array $data): Tenant
    {
        try {
            DB::beginTransaction();

            $tenant = TenantService::instance()->createTenant(tenant_client(), $data);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            fns()->sendDanger(__('fields.join_activity_failed_title'), __('fields.join_activity_failed_body'));
            $this->halt();
        }

        return $tenant;
    }

    protected function getFormActions(): array
    {
        if (Filament::auth()->check() && $this->hasReachedActivitiesLimit()) {
            return [
                Action::make('back')
                    ->label(__('messages.ok'))
                    ->url(url()->previous()),
            ];
        }

        return parent::getFormActions();
    }

    /** @param  array<int, string>  $alsoValidate */
    protected function afterStateValidate(array $alsoValidate = []): \Closure
    {
        return function (Component $component) use ($alsoValidate): void {
            $this->validateFormFields(array_merge([$component->getStatePath()], $alsoValidate));
        };
    }

    protected function validateFormField(string $statePath): void
    {
        try {
            $this->validateOnly($statePath);
        } catch (ValidationException) {
        }
    }

    /** @param  array<int, string>  $statePaths */
    protected function validateFormFields(array $statePaths): void
    {
        foreach ($statePaths as $statePath) {
            $this->validateFormField($statePath);
        }
    }

    public static function canView(): bool
    {
        if (! Filament::auth()->check()) {
            return true;
        }

        return filament()->auth()->user()?->hasRole(User::ROLE_CLIENT) ?? false;
    }
}
