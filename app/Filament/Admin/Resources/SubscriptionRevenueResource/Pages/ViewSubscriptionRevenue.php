<?php

namespace App\Filament\Admin\Resources\SubscriptionRevenueResource\Pages;

use App\Filament\Admin\Concerns\SharesSubscriptionInvoiceUrl;
use App\Filament\Admin\Resources\SubscriptionRevenueResource;
use App\Models\Subscription;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscriptionRevenue extends ViewRecord
{
    use SharesSubscriptionInvoiceUrl;

    protected static string $resource = SubscriptionRevenueResource::class;

    protected function getHeaderActions(): array
    {
        if ($this->record?->isFree()) {
            return [];
        }

        return [
            static::shareSubscriptionInvoiceUrlHeaderAction(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('fields.revenue_client_section'))
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('client.name')
                            ->label(__('fields.client')),
                        Infolists\Components\TextEntry::make('client.email')
                            ->label(__('fields.email')),
                        Infolists\Components\TextEntry::make('client.phone')
                            ->label(__('fields.phone')),
                    ]),

                Infolists\Components\Section::make(__('fields.revenue_subscription_section'))
                    ->icon('heroicon-o-briefcase')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('plan.name')
                            ->label(__('fields.subscription_plan')),
                        Infolists\Components\TextEntry::make('billing_period')
                            ->label(__('fields.billing_period'))
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'yearly' => __('fields.yearly'),
                                default => __('fields.monthly'),
                            }),
                        Infolists\Components\TextEntry::make('start_date')
                            ->label(__('fields.date'))
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('price_ex_tax')
                            ->label(__('fields.revenue_before_tax'))
                            ->formatStateUsing(fn ($state): string => format_amount((float) ($state ?? 0)))
                            ->visible(fn (Subscription $record): bool => ! $record->isFree()),
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->label(__('fields.revenue_discount'))
                            ->formatStateUsing(fn ($state): string => $state > 0 ? '- ' . format_amount((float) $state) : '—')
                            ->visible(fn (Subscription $record): bool => ! $record->isFree() && (float) ($record->discount_amount ?? 0) > 0),
                        Infolists\Components\TextEntry::make('coupon_code')
                            ->label(__('fields.code'))
                            ->placeholder('—')
                            ->visible(fn (Subscription $record): bool => ! $record->isFree() && filled($record->coupon_code)),
                        Infolists\Components\TextEntry::make('tax_amount')
                            ->label(__('fields.tax'))
                            ->formatStateUsing(fn ($state): string => format_amount((float) ($state ?? 0)))
                            ->visible(fn (Subscription $record): bool => ! $record->isFree()),
                        Infolists\Components\TextEntry::make('price')
                            ->label(__('fields.revenue_total'))
                            ->formatStateUsing(fn ($state, Subscription $record): string => $record->isFree()
                                ? __('fields.free')
                                : format_amount((float) $state))
                            ->weight('bold')
                            ->color(fn (Subscription $record): string => $record->isFree() ? 'success' : 'primary'),
                        Infolists\Components\TextEntry::make('free_plan_notice')
                            ->label('')
                            ->state(fn (): string => __('fields.revenue_free_plan_notice'))
                            ->visible(fn (Subscription $record): bool => $record->isFree())
                            ->color('success'),
                    ]),
            ]);
    }
}
