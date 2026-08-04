<?php

namespace App\Filament\Admin\Resources\ClientResource\Pages;

use App\Filament\Admin\Resources\ClientResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Rules\UniqueClientAttributeRule;
use App\Services\RoleService;
use App\Services\SubscriptionPricingService;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected static string $view = 'filament.admin.resources.clients.pages.create-client';

    protected ?string $maxContentWidth = 'full';

    public function getTitle(): string|Htmlable
    {
        return __('fields.create_client');
    }

    public function getHeading(): string|Htmlable
    {
        return __('fields.create_client');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('fields.create_client_hint');
    }

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()
            ->label(__('fields.create_client_submit'))
            ->icon('heroicon-o-check-circle');
    }

    public function form(Form $form): Form
    {
        $plans = Plan::active()->orderBy('price')->get();
        $pricing = SubscriptionPricingService::instance();

        $planOptions = $plans->mapWithKeys(fn (Plan $plan) => [
            $plan->id => $plan->name,
        ])->all();

        $buildPlanDescriptions = function (?string $billingPeriod) use ($plans, $pricing): array {
            $period = $pricing->normalizeBillingPeriod($billingPeriod);

            return $plans->mapWithKeys(function (Plan $plan) use ($pricing, $period) {
                $quote = $pricing->quote($plan, $period);

                if ($quote['is_free']) {
                    return [$plan->id => __('fields.free')];
                }

                $suffix = $period === SubscriptionPricingService::BILLING_YEARLY
                    ? __('fields.subscription_per_year')
                    : __('fields.subscription_per_month');

                $parts = [
                    $pricing->formatMoney($quote['total_inc_tax'], $quote['currency']) . ' ' . $suffix,
                    __('fields.subscription_price_breakdown_short', [
                        'ex_tax' => $pricing->formatMoney($quote['subtotal_ex_tax'], $quote['currency']),
                        'tax' => $pricing->formatMoney($quote['tax_amount'], $quote['currency']),
                        'vat' => rtrim(rtrim(number_format($quote['tax_percent'], 2, '.', ''), '0'), '.'),
                    ]),
                ];

                if ($period === SubscriptionPricingService::BILLING_YEARLY) {
                    $parts[] = __('fields.subscription_yearly_discount_note');
                }

                return [$plan->id => implode(' · ', $parts)];
            })->all();
        };

        return $form
            ->schema([
                Section::make(__('fields.client_details'))
                    ->description(__('fields.create_client_details_hint'))
                    ->icon('heroicon-o-user')
                    ->extraAttributes(['class' => 'create-client-section'])
                    ->schema([
                        TextInput::make('name')
                            ->label(__('fields.name'))
                            ->placeholder(__('fields.create_client_name_placeholder'))
                            ->autofocus()
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['default' => 3, 'md' => 1]),

                        TextInput::make('email')
                            ->label(__('fields.email'))
                            ->placeholder('name@example.com')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                $set('user_email', filled($state) ? trim($state) : null);
                            })
                            ->rules([
                                new UniqueClientAttributeRule(
                                    'email',
                                    'email',
                                ),
                            ])
                            ->columnSpan(['default' => 3, 'md' => 1]),

                        TextInput::make('address')
                            ->label(__('fields.address'))
                            ->placeholder(__('fields.create_client_address_placeholder'))
                            ->maxLength(255)
                            ->columnSpan(['default' => 3, 'md' => 1]),
                    ])
                    ->columns(3),

                Section::make(__('fields.subscription_plan'))
                    ->description(__('fields.create_client_plan_hint') . ' — ' . __('fields.subscription_prices_ex_tax_note'))
                    ->icon('heroicon-o-rectangle-stack')
                    ->extraAttributes(['class' => 'create-client-section'])
                    ->schema([
                        Radio::make('billing_period')
                            ->label(__('fields.subscription_billing_period'))
                            ->options([
                                SubscriptionPricingService::BILLING_MONTHLY => __('fields.monthly'),
                                SubscriptionPricingService::BILLING_YEARLY => __('fields.yearly'),
                            ])
                            ->descriptions([
                                SubscriptionPricingService::BILLING_MONTHLY => __('fields.subscription_billing_monthly_hint'),
                                SubscriptionPricingService::BILLING_YEARLY => __('fields.subscription_yearly_discount_note'),
                            ])
                            ->default(SubscriptionPricingService::BILLING_MONTHLY)
                            ->live()
                            ->required()
                            ->inline()
                            ->columnSpanFull(),

                        Radio::make('plan_id')
                            ->label(__('fields.subscription_plan'))
                            ->options($planOptions)
                            ->descriptions(fn (Get $get) => $buildPlanDescriptions($get('billing_period')))
                            ->required()
                            ->validationAttribute(__('fields.subscription_plan'))
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                                'lg' => max(1, min(3, count($planOptions))),
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make(__('fields.account_and_login_details'))
                    ->description(__('fields.create_client_login_hint'))
                    ->icon('heroicon-o-lock-closed')
                    ->extraAttributes(['class' => 'create-client-section'])
                    ->schema([
                        TextInput::make('user_email')
                            ->label(__('fields.login_email'))
                            ->email()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(new HtmlString(
                                '<span class="text-sm text-gray-500 dark:text-gray-400">'
                                . e(__('fields.create_client_login_email_helper'))
                                . '</span>'
                            ))
                            ->columnSpan(['default' => 3, 'lg' => 1]),

                        TextInput::make('user_password')
                            ->label(__('fields.password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->autocomplete('new-password')
                            ->helperText(__('fields.create_client_password_helper'))
                            ->columnSpan(['default' => 3, 'md' => 1]),

                        TextInput::make('user_password_confirmation')
                            ->label(__('fields.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('user_password')
                            ->autocomplete('new-password')
                            ->dehydrated(false)
                            ->columnSpan(['default' => 3, 'md' => 1]),
                    ])
                    ->columns(3),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['email'] = filled($data['email'] ?? null) ? trim($data['email']) : null;
        $data['name'] = filled($data['name'] ?? null) ? trim($data['name']) : null;
        $data['phone'] = null;
        $data['mobile'] = null;
        $data['self_registered'] = false;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $model = null;

        try {
            DB::beginTransaction();

            $plan = Plan::findOrFail($data['plan_id']);
            $nameParts = preg_split('/\s+/u', trim((string) $data['name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $user = User::create([
                'first_name' => $nameParts[0] ?? $data['name'],
                'second_name' => $nameParts[1] ?? null,
                'third_name' => $nameParts[2] ?? null,
                'fourth_name' => isset($nameParts[3])
                    ? implode(' ', array_slice($nameParts, 3))
                    : null,
                'email' => $data['email'],
                'password' => bcrypt($data['user_password']),
                'active' => 1,
            ]);

            RoleService::instance()->assignRole($user, User::ROLE_CLIENT);

            $data['user_id'] = $user->id;

            $model = parent::handleRecordCreation(Arr::except($data, [
                'plan_id',
                'billing_period',
                'user_password',
                'user_password_confirmation',
                'user_email',
                'user_first_name',
                'user_second_name',
                'user_third_name',
                'user_fourth_name',
                'user_phone',
            ]));

            Subscription::subscribe(
                $plan,
                $model,
                $data['billing_period'] ?? SubscriptionPricingService::BILLING_MONTHLY
            );

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);
            fns()->displayException($exception);
            $this->halt();
        }

        return $model;
    }
}
