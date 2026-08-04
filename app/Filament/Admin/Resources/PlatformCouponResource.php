<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PlatformCouponResource\Pages;
use App\Models\PlatformCoupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PlatformCouponResource extends Resource
{
    protected static ?string $model = PlatformCoupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $slug = 'subscription-coupons';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('fields.subscription_coupon');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fields.subscription_coupons');
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.subscription_coupons');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()->valid()->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('fields.code'))
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::upper(trim($state)) : $state)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                        Forms\Components\Radio::make('type')
                            ->label(__('fields.subscription_coupon_type'))
                            ->options([
                                PlatformCoupon::TYPE_PERCENT => __('fields.subscription_coupon_type_percent'),
                                PlatformCoupon::TYPE_FIXED => __('fields.subscription_coupon_type_fixed'),
                            ])
                            ->default(PlatformCoupon::TYPE_PERCENT)
                            ->required()
                            ->inline()
                            ->live(),

                        Forms\Components\TextInput::make('value')
                            ->label(fn (Get $get): string => $get('type') === PlatformCoupon::TYPE_FIXED
                                ? __('fields.subscription_coupon_fixed_value')
                                : __('fields.subscription_coupon_percent_value'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(fn (Get $get) => $get('type') === PlatformCoupon::TYPE_PERCENT ? 100 : null)
                            ->suffix(fn (Get $get): ?string => $get('type') === PlatformCoupon::TYPE_PERCENT ? '%' : main_currency_iso_code()),

                        Forms\Components\DateTimePicker::make('valid_until')
                            ->label(__('fields.subscription_coupon_valid_until'))
                            ->required()
                            ->native(false)
                            ->seconds(false),

                        Forms\Components\Toggle::make('active')
                            ->label(__('fields.active'))
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Textarea::make('description')
                            ->label(__('fields.description'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('fields.subscription_coupon_type'))
                    ->formatStateUsing(fn (string $state): string => $state === PlatformCoupon::TYPE_FIXED
                        ? __('fields.subscription_coupon_type_fixed')
                        : __('fields.subscription_coupon_type_percent'))
                    ->badge(),

                Tables\Columns\TextColumn::make('value')
                    ->label(__('fields.value'))
                    ->formatStateUsing(function ($state, PlatformCoupon $record): string {
                        if ($record->isPercent()) {
                            return rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.') . '%';
                        }

                        return trim(main_currency_iso_code() . ' ' . format_amount((float) $state));
                    }),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label(__('fields.subscription_coupon_valid_until'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->label(__('fields.active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('redemptions_count')
                    ->label(__('fields.subscription_coupon_redemptions'))
                    ->counts('redemptions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.created_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label(__('fields.active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformCoupons::route('/'),
            'create' => Pages\CreatePlatformCoupon::route('/create'),
            'edit' => Pages\EditPlatformCoupon::route('/{record}/edit'),
        ];
    }
}
