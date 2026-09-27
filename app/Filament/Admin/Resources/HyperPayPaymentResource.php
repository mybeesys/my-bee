<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\HyperPayPaymentResource\Pages;
use App\Models\HyperPayPayment;
use App\Services\HyperPay\HyperPayConfig;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HyperPayPaymentResource extends Resource
{
    protected static ?string $model = HyperPayPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $slug = 'hyperpay-payments';

    protected static ?int $navigationSort = 5;

    protected static bool $isScopedToTenant = false;

    protected static bool $shouldSkipAuthorization = true;

    public static function getModelLabel(): string
    {
        return __('fields.hyperpay_payment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fields.hyperpay_payments');
    }

    public static function getNavigationLabel(): string
    {
        return __('fields.hyperpay_payments');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('status', HyperPayPayment::STATUS_PAID)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('fields.hyperpay_payment'))
                ->icon('heroicon-o-credit-card')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('status')
                        ->label(__('fields.status'))
                        ->badge()
                        ->formatStateUsing(fn ($state, HyperPayPayment $record) => $record->statusLabel())
                        ->color(fn ($state, HyperPayPayment $record) => $record->statusColor()),
                    Infolists\Components\TextEntry::make('amount')
                        ->label(__('fields.amount'))
                        ->formatStateUsing(fn ($state, HyperPayPayment $record) => $record->currency.' '.format_amount((float) $state)),
                    Infolists\Components\TextEntry::make('brand')
                        ->label(__('fields.hyperpay_brand'))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('merchant_transaction_id')
                        ->label(__('fields.hyperpay_merchant_transaction_id'))
                        ->copyable(),
                    Infolists\Components\TextEntry::make('checkout_id')
                        ->label(__('fields.hyperpay_checkout_id'))
                        ->placeholder('—')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('payment_id')
                        ->label(__('fields.hyperpay_payment_id'))
                        ->placeholder('—')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('result_code')
                        ->label(__('fields.hyperpay_result_code'))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('result_description')
                        ->label(__('fields.hyperpay_result_description'))
                        ->columnSpanFull()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('paid_at')
                        ->label(__('fields.paid_at'))
                        ->dateTime()
                        ->placeholder('—'),
                ]),
            Infolists\Components\Section::make(__('fields.client'))
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('client.name')->label(__('fields.client')),
                    Infolists\Components\TextEntry::make('client.email')->label(__('fields.email')),
                    Infolists\Components\TextEntry::make('plan.name')->label(__('fields.subscription_plan')),
                    Infolists\Components\TextEntry::make('billing_period')
                        ->label(__('fields.billing_period'))
                        ->formatStateUsing(fn (?string $state) => $state === 'yearly' ? __('fields.yearly') : __('fields.monthly')),
                    Infolists\Components\TextEntry::make('coupon_code')
                        ->label(__('fields.subscription_coupon'))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('subscription.invoice_no')
                        ->label(__('fields.invoice_no'))
                        ->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label(__('fields.client'))
                    ->searchable()
                    ->description(fn (HyperPayPayment $record) => $record->client?->email),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('fields.subscription_plan'))
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('fields.amount'))
                    ->formatStateUsing(fn ($state, HyperPayPayment $record) => $record->currency.' '.format_amount((float) $state)),
                Tables\Columns\TextColumn::make('brand')
                    ->label(__('fields.hyperpay_brand'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state, HyperPayPayment $record) => $record->statusLabel())
                    ->color(fn ($state, HyperPayPayment $record) => $record->statusColor()),
                Tables\Columns\TextColumn::make('result_code')
                    ->label(__('fields.hyperpay_result_code'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.date'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('fields.status'))
                    ->options([
                        HyperPayPayment::STATUS_PAID => __('fields.hyperpay_status_paid'),
                        HyperPayPayment::STATUS_FAILED => __('fields.hyperpay_status_failed'),
                        HyperPayPayment::STATUS_CHECKOUT => __('fields.hyperpay_status_checkout'),
                        HyperPayPayment::STATUS_PENDING => __('fields.hyperpay_status_pending'),
                        HyperPayPayment::STATUS_PENDING_GATEWAY => __('fields.hyperpay_status_pending_gateway'),
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('settings')
                    ->label(__('fields.hyperpay_open_settings'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(\App\Filament\Admin\Pages\Settings::getUrl())
                    ->openUrlInNewTab(false),
                Tables\Actions\Action::make('merchant_portal')
                    ->label(__('fields.hyperpay_merchant_portal'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn () => HyperPayConfig::instance()->merchantPortalUrl() ?: null)
                    ->visible(fn () => filled(HyperPayConfig::instance()->merchantPortalUrl()))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'plan', 'subscription']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHyperPayPayments::route('/'),
            'view' => Pages\ViewHyperPayPayment::route('/{record}'),
        ];
    }
}
