<?php

namespace App\Filament\Admin\Resources\ClientResource\Pages;

use App\Filament\Admin\Resources\ClientResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Rules\UniqueClientAttributeRule;
use App\Services\RoleService;
use App\Services\SubscriptionInvoiceAdjustmentService;
use App\Services\SubscriptionPricingService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

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
                    ->description(__('fields.create_client_plan_hint'))
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
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set) use ($pricing) {
                                $plan = Plan::query()->find($state);

                                if (! $plan) {
                                    $set('apply_admin_discount', false);

                                    return;
                                }

                                $quote = $pricing->quote($plan, $get('billing_period'));

                                if ($quote['is_free'] || (float) $quote['total_inc_tax'] <= 0) {
                                    $set('apply_admin_discount', false);
                                }
                            })
                            ->validationAttribute(__('fields.subscription_plan'))
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                                'lg' => max(1, min(3, count($planOptions))),
                            ])
                            ->columnSpanFull(),

                        Toggle::make('apply_admin_discount')
                            ->label(__('fields.revenue_admin_discount'))
                            ->helperText(__('fields.create_client_admin_discount_helper'))
                            ->live()
                            ->default(false)
                            ->inline(false)
                            ->disabled(function (Get $get) use ($pricing): bool {
                                $plan = Plan::query()->find($get('plan_id'));

                                if (! $plan) {
                                    return true;
                                }

                                $quote = $pricing->quote($plan, $get('billing_period'));

                                return $quote['is_free'] || (float) $quote['total_inc_tax'] <= 0;
                            })
                            ->dehydrated()
                            ->columnSpanFull(),

                        TextInput::make('admin_discount_percent')
                            ->label(__('fields.revenue_admin_discount_percent'))
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->live(onBlur: true)
                            ->required(fn (Get $get): bool => (bool) $get('apply_admin_discount'))
                            ->visible(fn (Get $get): bool => (bool) $get('apply_admin_discount'))
                            ->helperText(__('fields.revenue_admin_discount_percent_hint'))
                            ->columnSpan(['default' => 1, 'md' => 1]),

                        Textarea::make('admin_discount_note')
                            ->label(__('fields.revenue_admin_discount_note'))
                            ->rows(2)
                            ->maxLength(500)
                            ->visible(fn (Get $get): bool => (bool) $get('apply_admin_discount'))
                            ->placeholder(__('fields.revenue_admin_discount_note_placeholder'))
                            ->columnSpan(['default' => 1, 'md' => 2]),

                        Placeholder::make('admin_discount_preview')
                            ->label(__('fields.revenue_admin_discount_preview'))
                            ->visible(fn (Get $get): bool => (bool) $get('apply_admin_discount'))
                            ->content(function (Get $get) use ($pricing): HtmlString {
                                $percent = (float) ($get('admin_discount_percent') ?? 0);
                                $plan = Plan::query()->find($get('plan_id'));

                                if (! $plan || $percent <= 0 || $percent > 100) {
                                    return new HtmlString(
                                        '<p class="text-sm text-gray-500">' . e(__('fields.revenue_admin_discount_preview_empty')) . '</p>'
                                    );
                                }

                                $quote = $pricing->quote($plan, $get('billing_period'));

                                if ($quote['is_free'] || (float) $quote['total_inc_tax'] <= 0) {
                                    return new HtmlString(
                                        '<p class="text-sm text-danger-600">' . e(__('fields.revenue_admin_discount_free_plan')) . '</p>'
                                    );
                                }

                                try {
                                    $preview = SubscriptionInvoiceAdjustmentService::instance()
                                        ->previewFromQuote($quote, $percent);
                                } catch (ValidationException) {
                                    return new HtmlString(
                                        '<p class="text-sm text-danger-600">' . e(__('fields.revenue_admin_discount_percent_invalid')) . '</p>'
                                    );
                                }

                                $fmt = fn (float $amount): string => $pricing->formatMoney($amount, $preview['currency']);

                                return new HtmlString(
                                    '<div class="create-client-discount-preview">'
                                    . '<p>' . e(__('fields.revenue_admin_discount_preview_original')) . ': <strong>' . e($fmt($preview['original_total_inc_tax'])) . '</strong></p>'
                                    . '<p>' . e(__('fields.revenue_admin_discount')) . ': <strong>' . e($preview['percent']) . '%</strong></p>'
                                    . '<p>' . e(__('fields.revenue_admin_discount_preview_recognized')) . ': <strong>' . e($fmt($preview['total_inc_tax'])) . '</strong></p>'
                                    . '<p class="create-client-discount-preview__waived">' . e(__('fields.revenue_admin_discount_preview_waived')) . ': <strong>' . e($fmt($preview['waived_inc_tax'])) . '</strong></p>'
                                    . '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 3]),

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

        $selectedPlan = Plan::query()->find($data['plan_id'] ?? null);
        $selectedQuote = $selectedPlan
            ? SubscriptionPricingService::instance()->quote(
                $selectedPlan,
                $data['billing_period'] ?? null
            )
            : null;

        if (! $selectedQuote || $selectedQuote['is_free'] || (float) $selectedQuote['total_inc_tax'] <= 0) {
            $data['apply_admin_discount'] = false;
        }

        if (! empty($data['apply_admin_discount'])) {
            $percent = (float) ($data['admin_discount_percent'] ?? 0);

            if ($percent <= 0 || $percent > 100) {
                throw ValidationException::withMessages([
                    'admin_discount_percent' => __('fields.revenue_admin_discount_percent_invalid'),
                ]);
            }
        }

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
                'apply_admin_discount',
                'admin_discount_percent',
                'admin_discount_note',
                'user_password',
                'user_password_confirmation',
                'user_email',
                'user_first_name',
                'user_second_name',
                'user_third_name',
                'user_fourth_name',
                'user_phone',
            ]));

            $adminPercent = ! empty($data['apply_admin_discount'])
                ? (float) ($data['admin_discount_percent'] ?? 0)
                : 0.0;

            Subscription::subscribe(
                $plan,
                $model,
                $data['billing_period'] ?? SubscriptionPricingService::BILLING_MONTHLY,
                null,
                $adminPercent > 0 ? $adminPercent : null,
                $adminPercent > 0 ? ($data['admin_discount_note'] ?? null) : null,
                auth()->id(),
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
