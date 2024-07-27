<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PlanResource\Pages;
use App\Filament\Admin\Resources\PlanResource\RelationManagers;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use function Filament\Support\format_money;

class PlanResource extends Resource
{
    use Translatable;

    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = "heroicon-o-briefcase";

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('fields.subscription_plan');
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.subscription_plans');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fields.subscription_plans');
    }

//    public static function getNavigationGroup(): ?string
//    {
//        return __('fields.subscription_plans');
//    }

    public static function getTranslatableLocales(): array
    {
        return config('system.supported_languages', []);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([

                        Forms\Components\TextInput::make('name')
                            ->label(__('fields.name'))
                            ->autofocus()
                            ->required(),

                        Forms\Components\Select::make('span')
                            ->label(__('fields.span'))
                            ->reactive()
                            ->default(Plan::SPAN_SPECIFIED)
                            ->options([
                                Plan::SPAN_SPECIFIED => __('fields.specified'),
                                Plan::SPAN_ONE_TIME => __('fields.one_time_subscription'),
                            ])
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state == Plan::SPAN_ONE_TIME)
                                    $set('span_in_days', -1);
                                else
                                    $set('span_in_days', 30);

                            })
                            ->required(),

                        Forms\Components\TextInput::make('span_in_days')
                            ->label(__('fields.span_in_days'))
                            ->visible(fn(Forms\Get $get) => $get('span') === Plan::SPAN_SPECIFIED)
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1096) //3 years
                            ->required(),


                        Forms\Components\TextInput::make('price')
                            ->label(__('fields.price'))
                            ->live(true)
                            ->numeric()
                            ->formatStateUsing(fn($state) => $state ? number_format($state, 2, '.', '') : null)
                            ->minValue(0)
                            ->maxValue(500000)

                            ->required(),
                    ])->columns(4),


                Forms\Components\Section::make(__('fields.subscription_plan_features'))->schema([

                    Forms\Components\TextInput::make('max_allowed_companies')
                        ->label(__('fields.max_allowed_companies'))
                        ->visible(fn(Forms\Get $get) => $get('unlimited_companies') === false)
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(1000)
                        ->columnSpan(1)
                        ->required(),

                    Forms\Components\TextInput::make('max_allowed_purchase_invoices')
                        ->label(__('fields.max_allowed_purchase_invoices'))
                        ->visible(fn(Forms\Get $get) => $get('unlimited_purchase_invoices') === false)
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(9000000)
                        ->required(),


                    Forms\Components\TextInput::make('max_allowed_sales_invoices')
                        ->label(__('fields.max_allowed_sales_invoices'))
                        ->visible(fn(Forms\Get $get) => $get('unlimited_sales_invoices') === false)
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(9000000)
                        ->required(),

                    Forms\Components\TextInput::make('max_allowed_users')
                        ->label(__('fields.max_allowed_users'))
                        ->visible(fn(Forms\Get $get) => $get('unlimited_users') === false)
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(9000000)
                        ->columnSpan(1)
                        ->required(),

                    Forms\Components\Toggle::make('enable_roles')
                        ->label(__('fields.enable_roles'))
                        ->default(0),

                    Forms\Components\Section::make()
                        ->schema([

                            Forms\Components\Checkbox::make('unlimited_companies')
                                ->label(__('fields.unlimited_companies'))
                                ->dehydrated(false)
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if ($state === true) {
                                        $set('max_allowed_companies', -1);
                                    } else {
                                        $set('max_allowed_companies', 1);
                                    }
                                })->columnSpanFull()
                                ->reactive(),

                            Forms\Components\Checkbox::make('unlimited_users')
                                ->label(__('fields.unlimited_users'))
                                ->dehydrated(false)
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if ($state === true) {
                                        $set('max_allowed_users', -1);
                                    } else {
                                        $set('max_allowed_user', 1);
                                    }
                                })->columnSpanFull()
                                ->reactive(),

                            Forms\Components\Checkbox::make('unlimited_purchase_invoices')
                                ->label(__('fields.unlimited_purchase_invoices'))
                                ->dehydrated(false)
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if ($state === true) {
                                        $set('max_allowed_purchase_invoices', -1);
                                    } else {
                                        $set('max_allowed_purchase_invoices', 5);
                                    }
                                })
                                ->reactive(),

                            Forms\Components\Checkbox::make('unlimited_sales_invoices')
                                ->label(__('fields.unlimited_sales_invoices'))
                                ->dehydrated(false)
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if ($state === true) {
                                        $set('max_allowed_sales_invoices', -1);
                                    } else {
                                        $set('max_allowed_sales_invoices', 5);
                                    }
                                })
                                ->reactive(),

                        ]),

                ])->columns(4),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Toggle::make('restrict_account_after_period')
                            ->label(__('fields.restrict_account_after_period'))
                            ->dehydrated(false)
                            ->helperText(__("fields.use_this_feature_with_trial_plans_only"))
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state === true) {
                                    $set('restrict_account_after_days', 14);
                                } else {
                                    $set('restrict_account_after_days', -1);
                                }
                            }),

                        Forms\Components\TextInput::make('restrict_account_after_days')
                            ->visible(fn(Forms\Get $get) => $get('restrict_account_after_period') === true)
                            ->label(__('fields.restrict_account_after_days'))
                            ->default(-1)
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->columnSpan(1)
                            ->required(),
                    ])->columns(4),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Checkbox::make('active')
                            ->label(__('fields.active'))
                            ->default(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->label(__('fields.name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('span')
                    ->label(__('fields.span'))
                    ->formatStateUsing(fn($state) => __("fields.plan_span_$state"))
                    ->description(fn(Plan $record) => $record->span === Plan::SPAN_ONE_TIME ? "∞" : $record->span_in_days)
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('fields.price'))
                    ->prefix("SAR ")
                    ->formatStateUsing(fn($state) => format_amount($state))
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('max_allowed_companies')
                    ->label(__('fields.max_allowed_companies'))
                    ->toggleable()
                    ->formatStateUsing(fn($state) => $state == -1 ? __('fields.unlimited') : $state),

                Tables\Columns\TextColumn::make('max_allowed_users')
                    ->label(__('fields.max_allowed_users'))
                    ->toggleable()
                    ->formatStateUsing(fn($state) => $state == -1 ? __('fields.unlimited') : $state),

                Tables\Columns\TextColumn::make('max_allowed_purchase_invoices')
                    ->label(__('fields.max_allowed_purchase_invoices'))
                    ->toggleable()
                    ->formatStateUsing(fn($state) => $state == -1 ? __('fields.unlimited') : $state),

                Tables\Columns\TextColumn::make('max_allowed_sales_invoices')
                    ->label(__('fields.max_allowed_sales_invoices'))
                    ->toggleable()
                    ->formatStateUsing(fn($state) => $state == -1 ? __('fields.unlimited') : $state),

                Tables\Columns\TextColumn::make('clients_count')
                    ->label(__('fields.subscribers_count'))
                    ->toggleable()
                    ->counts('clients'),

                Tables\Columns\IconColumn::make('active')
                    ->label(__('fields.active'))
                    ->toggleable()
                    ->boolean(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->action(function ($record) {
                        try {
                            $record->delete();
                            fns()->deleted();
                        }catch (\Throwable $exception){fns()->displayException($exception);}
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['clients', 'subscriptions']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
