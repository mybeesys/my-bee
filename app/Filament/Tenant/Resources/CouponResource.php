<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CouponResource\Pages;
use App\Filament\Tenant\Resources\CouponResource\RelationManagers;
use App\Models\Coupon;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Rules\UniqueTenantItemRule;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $recordTitleAttribute = "code";

    protected static ?string $slug = "shop/coupons";

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('fields.coupon');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fields.coupons');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return plan_allows_store();
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && plan_allows_store();
    }

    public static function getNavigationGroup(): ?string
    {
        return user_setting('fav.coupons', false) ? __('fields.navigation_group_favourites') : __('fields.nav_group_online_store');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([

                    TextInput::make('code')
                        ->label(__('fields.code'))
                        ->autofocus()
                        ->required()
                        ->rules([new UniqueTenantItemRule(Coupon::class, 'code', $form->getRecord()?->id)]),


                    Select::make('span')
                        ->required()
                        ->label(__('fields.coupon_validity'))
                        ->live()
                        ->options([
                            'one-time' => __('fields.coupon_span_one_time'),
                            'specified-time' => __('fields.coupon_span_specified_time'),
                            'unlimited-time' => __('fields.coupon_span_unlimited_time'),
                        ]),

                    DatePicker::make('valid_until')
                        ->hidden(fn(Forms\Get $get) => $get('span') === 'unlimited-time')
                        ->label(__('fields.valid_until'))
                        ->required()
                        ->minDate(today())
                        ->maxDate(now()->addYears(50))
                        ->default(now()->addMonth())
                        ->format('Y-m-d')
                        ->displayFormat('d/m/Y'),

                ])->columns(3),


                Forms\Components\Section::make([
                    Radio::make('type')
                        ->label(__('fields.type'))
                        ->inline()
                        ->inlineLabel(false)
                        ->columnSpanFull()
                        ->live()
                        ->required()
                        ->default(Coupon::$TYPE_PERCENT)
                        ->options([
                            Coupon::$TYPE_FIXED => __('fields.coupon_type_fixed'),
                            Coupon::$TYPE_PERCENT => __('fields.coupon_type_percent'),
                        ])
                        ->descriptions([

                        ]),

                    Forms\Components\TextInput::make('percent')
                        ->label(__('fields.the_percentage'))
                        ->live()
                        ->visible(fn(Forms\Get $get) => $get('type') === Coupon::$TYPE_PERCENT)
                        ->dehydrated(fn(Forms\Get $get) => $get('type') === Coupon::$TYPE_PERCENT)
                        ->required()
                        ->default(1)
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(99)
                        ->extraInputAttributes(['min' => 1, 'max' => 99]),

                    Forms\Components\TextInput::make('amount')
                        ->label(__('fields.amount'))
                        ->live()
                        ->visible(fn(Forms\Get $get) => $get('type') === Coupon::$TYPE_FIXED)
                        ->dehydrated(fn(Forms\Get $get) => $get('type') === Coupon::$TYPE_FIXED)
                        ->required()
                        ->numeric()
                        ->minValue(1),
                ])->columns(4),

                Forms\Components\Section::make([
                    Checkbox::make('active')
                        ->label(__('fields.active'))
                        ->default(true),
                ]),

                Forms\Components\Section::make([
                    Forms\Components\RichEditor::make('description')
                        ->label(__('fields.description')),
                ]),

                View::make('components.loading'),
            ]);
    }


    public static function table(Tables\Table $table): Tables\Table
    {

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('fields.code'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('fields.type'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('value')
                    ->label(__('fields.value'))
                    ->getStateUsing(function ($record) {
                        if ($record->type == Coupon::$TYPE_FIXED) {
                            return number_format($record->value, 2) . " ".main_currency_iso_code();
                        }
                        return $record->value . "%";
                    }),

                Tables\Columns\TextColumn::make('usages_count')
                    ->label(__('fields.coupon_usages'))
                    ->counts('usages'),

                Tables\Columns\IconColumn::make('active')
                    ->label(__('fields.active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label(__('fields.valid_until'))
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['usages'])->latest();
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
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
