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
        $monthAdminWaived = (clone $monthQuery)
            ->whereNotNull('original_price')
            ->get(['original_price', 'price'])
            ->sum(fn (Subscription $row): float => max(0, (float) $row->original_price - (float) $row->price));
        $monthCount = (clone $monthQuery)->count();

        return [
            Stat::make(__('fields.revenue_total_all_time'), format_amount($totalRevenue))
                ->description(__('fields.admin_dashboard_all_time'))
                ->color(Color::Emerald)
                ->icon('heroicon-o-banknotes'),

            Stat::make(__('fields.revenue_total_this_month'), format_amount($monthRevenue))
                ->description(__('fields.revenue_subscriptions_month') . ': ' . $monthCount)
                ->color(Color::Amber)
                ->icon('heroicon-o-calendar-days'),

            Stat::make(__('fields.revenue_tax_this_month'), format_amount($monthTax))
                ->description(__('fields.revenue_vat_collected'))
                ->color(Color::Sky)
                ->icon('heroicon-o-receipt-percent'),

            Stat::make(__('fields.revenue_admin_waived_this_month'), format_amount((float) $monthAdminWaived))
                ->description(__('fields.revenue_admin_waived_short'))
                ->color(Color::Orange)
                ->icon('heroicon-o-receipt-percent'),
        ];
    }
}
