<?php

namespace App\Filament\Admin\Resources\SubscriptionRevenueResource\Widgets;

use App\Models\Subscription;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverviewWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $monthQuery = Subscription::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);

        $totalRevenue = (float) Subscription::query()->sum('price');
        $monthRevenue = (float) (clone $monthQuery)->sum('price');
        $monthTax = (float) (clone $monthQuery)->sum('tax_amount');
        $monthDiscount = (float) (clone $monthQuery)->sum('discount_amount');
        $monthCount = (clone $monthQuery)->count();

        return [
            Stat::make(__('fields.revenue_total_all_time'), format_amount($totalRevenue))
                ->description(__('fields.admin_dashboard_all_time'))
                ->color(Color::Emerald)
                ->icon('heroicon-o-banknotes'),

            Stat::make(__('fields.revenue_total_this_month'), format_amount($monthRevenue))
                ->description(__('fields.admin_dashboard_this_month'))
                ->color(Color::Amber)
                ->icon('heroicon-o-calendar-days'),

            Stat::make(__('fields.revenue_tax_this_month'), format_amount($monthTax))
                ->description(__('fields.revenue_vat_collected'))
                ->color(Color::Sky)
                ->icon('heroicon-o-receipt-percent'),

            Stat::make(__('fields.revenue_subscriptions_month'), (string) $monthCount)
                ->description(__('fields.revenue_discount') . ': ' . format_amount($monthDiscount))
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color($monthDiscount > 0 ? Color::Orange : Color::Green)
                ->icon('heroicon-o-document-text'),
        ];
    }
}
