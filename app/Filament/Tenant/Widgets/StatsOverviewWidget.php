<?php

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Concerns\BuildsMonthlySparklineChart;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    use BuildsMonthlySparklineChart;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('fields.dashboard_stats_overview');
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $newClients = Customer::thisWeek()->count();
        $newOrders = Order::new()->thisWeek()->count();
        $salesThisMonth = Invoice::sales()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $completedOrders = Order::completed()->count();

        return [
            Stat::make(__('fields.new_clients'), $newClients)
                ->description(__('fields.this_week'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color(Color::Sky)
                ->chart($this->monthlyCountChart(Customer::query()))
                ->icon('heroicon-o-user-group'),

            Stat::make(__('fields.new_orders'), $newOrders)
                ->description(__('fields.this_week'))
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color(Color::Amber)
                ->chart($this->monthlyCountChart(Order::query()))
                ->icon('heroicon-o-shopping-cart'),

            Stat::make(__('fields.sales_invoices'), $salesThisMonth)
                ->description(__('fields.dashboard_this_month'))
                ->descriptionIcon('heroicon-m-document-text')
                ->color(Color::Emerald)
                ->chart($this->monthlyCountChart(Invoice::query()->sales()))
                ->icon('heroicon-o-document-check'),

            Stat::make(__('fields.completed_orders'), $completedOrders)
                ->description(__('fields.dashboard_all_time'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color(Color::Green)
                ->icon('heroicon-o-check-circle'),
        ];
    }
}
