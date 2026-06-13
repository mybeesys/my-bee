<?php

namespace App\Filament\Tenant\Resources\OrderResource\Widgets;

use App\Filament\Tenant\Concerns\BuildsMonthlySparklineChart;
use App\Filament\Tenant\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Services\CacheService;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStats extends BaseWidget
{
    use BuildsMonthlySparklineChart;
    use InteractsWithPageTable;

    protected static ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getTablePage(): string
    {
        return ListOrders::class;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('fields.all_orders'), Order::count())
                ->icon('heroicon-o-clipboard-document-list')
                ->chart($this->monthlyCountChart(Order::query())),

            Stat::make(__('fields.new_orders'), Order::new()->count())
                ->icon('heroicon-o-sparkles'),

            Stat::make(__('fields.in_packaging_orders'), Order::packaging()->count())
                ->icon('heroicon-o-archive-box'),

            Stat::make(__('fields.delivery_in_progress_orders'), Order::deliveryInProgress()->count())
                ->icon('heroicon-o-truck'),

            Stat::make(__('fields.completed_orders'), Order::completed()->count())
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make(__('fields.cancelled_orders'), Order::cancelled()->count())
                ->icon('heroicon-o-x-circle')
                ->color('danger'),

            Stat::make(__('fields.revenue'), $this->getRevenue())
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->description(__('fields.revenue_stat_desc'))
                ->descriptionIcon('heroicon-o-currency-dollar'),
        ];
    }

    protected function getRevenue(): string
    {
        $orders = CacheService::instance()->remember('orders', 60, function () {
            return Order::completed()->with(['invoice.salesPayments'])->get();
        }, true);

        $revenue = 0;

        foreach ($orders as $order) {
            if ($order->invoice) {
                $revenue += $order->invoice->total_paid;
            }
        }

        return format_amount($revenue, includeCurrencyCode: true);
    }
}
