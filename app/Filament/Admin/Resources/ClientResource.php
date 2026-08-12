<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ClientResource\Pages;
use App\Filament\Admin\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\InternationalPhoneRule;
use App\Rules\TenantEmailRule;
use App\Rules\TenantPhoneRule;
use App\Rules\UniqueClientAttributeRule;
use App\Services\TenantService;
use App\Services\SubscriptionPricingService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\ActionSize;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = "heroicon-o-user-group";

    protected static ?string $slug = "clients";

    protected static ?string $recordTitleAttribute = "name";

    protected static ?int $navigationSort = 1;


    public static function getModelLabel(): string
    {
        return __('fields.client');
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.clients');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fields.clients');
    }

//    public static function getNavigationGroup(): ?string
//    {
//        return __('fields.clients');
//    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('fields.client_details'))
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('email')
                            ->label(__('fields.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                new UniqueClientAttributeRule(
                                    'email',
                                    'email',
                                    ignore_client_id: $form->getRecord()?->id,
                                    ignore_user_id: $form->getRecord()?->user_id
                                ),
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('phone')
                            ->label(__('fields.phone'))
                            ->tel()
                            ->nullable()
                            ->rules([
                                fn () => function (string $attribute, $value, $fail) use ($form) {
                                    if (! filled($value)) {
                                        return;
                                    }

                                    (new UniqueClientAttributeRule(
                                        'phone',
                                        'phone',
                                        ignore_client_id: $form->getRecord()?->id,
                                        ignore_user_id: $form->getRecord()?->user_id
                                    ))->validate($attribute, $value, $fail);
                                },
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('mobile')
                            ->label(__('fields.mobile'))
                            ->tel()
                            ->nullable()
                            ->rules([
                                fn () => function (string $attribute, $value, $fail) use ($form) {
                                    if (! filled($value)) {
                                        return;
                                    }

                                    (new UniqueClientAttributeRule(
                                        'mobile',
                                        ignore_client_id: $form->getRecord()?->id
                                    ))->validate($attribute, $value, $fail);
                                },
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('address')
                            ->label(__('fields.address'))
                            ->maxLength(255)
                            ->columnSpanFull(),

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

                        Select::make('plan_id')
                            ->label(__('fields.subscription_plan'))
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->options(Plan::active()->orderBy('price')->pluck('name', 'id'))
                            ->helperText(function (Get $get) {
                                $planId = $get('plan_id');
                                if (! $planId) {
                                    return null;
                                }

                                $plan = Plan::find($planId);
                                if (! $plan) {
                                    return null;
                                }

                                $quote = subscription_pricing($plan, $get('billing_period'));
                                $pricing = SubscriptionPricingService::instance();

                                if ($quote['is_free']) {
                                    return __('fields.free');
                                }

                                return __('fields.subscription_admin_price_helper', [
                                    'total' => $pricing->formatMoney($quote['total_inc_tax'], $quote['currency']),
                                    'ex_tax' => $pricing->formatMoney($quote['subtotal_ex_tax'], $quote['currency']),
                                    'tax' => $pricing->formatMoney($quote['tax_amount'], $quote['currency']),
                                    'vat' => rtrim(rtrim(number_format($quote['tax_percent'], 2, '.', ''), '0'), '.'),
                                ]);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->toggleable()
                    ->description(fn(Client $record) => __('fields.activities') . " ({$record->tenants->count()})")
                    ->tooltip(fn(Client $record) => implode(', ', $record->tenants->pluck('name')->toArray()))
                    ->searchable(),

                Tables\Columns\TextColumn::make('subscription.plan.name')
                    ->label(__('fields.subscription'))
                    ->badge()
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('fields.phone'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('mobile')
                    ->label(__('fields.mobile'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('fields.email'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('address')
                    ->label(__('fields.address'))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.join_date'))
//                    ->dateTime('M j, Y')
                    ->sortable(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('add_activity')
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
                                    ->rules(
                                        [
                                            new InternationalPhoneRule(false),
                                            new TenantPhoneRule($record),
                                        ]
                                    ),

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
                    ->action(function (array $data, Client $record, Tables\Actions\Action $action) {
                        try {
                            DB::beginTransaction();

                            TenantService::instance()->createTenant($record, $data);

                            DB::commit();

                            fns()->saved();

                        } catch (\Throwable $exception) {
                            DB::rollBack();
                            report($exception);
                            fns()->sendDanger("تعذر إضافة النشاط", 'حدث خطأ أثناء إنشاء النشاط.');
                            $action->halt();
                        }
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['subscription', 'user', 'tenants'])->latest(); // TODO: Change the autogenerated stub
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
            'view' => Pages\ViewClient::route('/{record}/view'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Tabs::make()->schema([
                Tabs\Tab::make(__('fields.client_details'))
                    ->schema([
                        \Filament\Infolists\Components\Section::make()
                            ->schema([
                                TextEntry::make('name')->label(__('fields.name')),
                                TextEntry::make('phone')->label(__('fields.phone')),
                                TextEntry::make('mobile')->label(__('fields.mobile')),
                                TextEntry::make('email')->label(__('fields.email')),
                                TextEntry::make('address')->label(__('fields.address')),
                                TextEntry::make('created_at')->label(__('fields.join_date')),
                            ])->columns(2),
                    ]),
                Tabs\Tab::make(__('fields.subscription_plan'))
                    ->schema([
                        \Filament\Infolists\Components\Section::make()
                            ->schema([
                                TextEntry::make('subscription.plan.name')->label(__('fields.subscription')),
                                TextEntry::make('subscription.billing_period')
                                    ->label(__('fields.subscription_billing_period'))
                                    ->formatStateUsing(fn ($state) => $state === 'yearly' ? __('fields.yearly') : __('fields.monthly')),
                                TextEntry::make('subscription.price_ex_tax')
                                    ->label(__('fields.subscription_subtotal_ex_tax'))
                                    ->formatStateUsing(fn ($state) => $state === null ? '—' : main_currency_iso_code() . ' ' . format_amount($state)),
                                TextEntry::make('subscription.tax_amount')
                                    ->label(__('fields.tax'))
                                    ->formatStateUsing(fn ($state, $record) => $state === null
                                        ? '—'
                                        : main_currency_iso_code() . ' ' . format_amount($state)
                                            . ' (' . rtrim(rtrim(number_format((float) ($record->subscription?->tax_percent ?? 0), 2, '.', ''), '0'), '.') . '%)'),
                                TextEntry::make('subscription.price')
                                    ->label(__('fields.subscription_total_inc_tax'))
                                    ->formatStateUsing(fn ($state) => $state === null ? '—' : main_currency_iso_code() . ' ' . format_amount($state)),
                                TextEntry::make('subscription.start_date')->label(__('fields.start_date')),
                            ])->columns(2),
                    ]),
                Tabs\Tab::make(__('fields.account_and_login_details'))
                    ->schema([
                        \Filament\Infolists\Components\Section::make()
                            ->schema([
                                TextEntry::make('user.full_name')->label(__('fields.full_name')),
                                TextEntry::make('user.phone')->label(__('fields.phone')),
                                TextEntry::make('user.email')->label(__('fields.email')),
                                TextEntry::make('user.last_seen')->label(__('fields.last_seen')),
                            ])->columns(2),
                    ]),
            ])->columnSpanFull(),

        ]);
    }

}
