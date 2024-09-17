<?php

namespace App\Filament\Tenant\Resources\OrderResource\Widgets;

use App\Filament\Tenant\Resources\OrderResource\Pages\ListOrders;
use App\Helpers\CacheManager;
use App\Models\Order;
use App\Services\CacheService;
use Filament\Support\Colors\Color;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class OrderStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected static ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListOrders::class;
    }

    protected function getStats(): array
    {
        $orderData = Trend::model(Order::class)
            ->between(
                start: now()->subYear(),
                end: now(),
            )
            ->perMonth()
            ->count();

        return [
            Stat::make(__('fields.all_orders'), Order::count())
                ->chart(
                    $orderData
                        ->map(fn (TrendValue $value) => $value->aggregate)
                        ->toArray()
                ),
            Stat::make(__('fields.new_orders'), Order::new()->count()),
            Stat::make(__('fields.in_packaging_orders'), Order::packaging()->count()),
            Stat::make(__('fields.delivery_in_progress_orders'), Order::deliveryInProgress()->count()),
            Stat::make(__('fields.completed_orders'), Order::completed()->count())->color('success'),
            Stat::make(__('fields.cancelled_orders'), Order::cancelled()->count())->color('danger'),

            Stat::make(__('fields.revenue'), $this->getRevenue())
                ->color(Color::Green)
                ->description(__('fields.revenue_stat_desc'))
                ->descriptionIcon('heroicon-o-currency-dollar'),

//            Stat::make('Average price', number_format($this->getPageTableQuery()->avg('total_price'), 2)),

        ];
    }

    protected function getRevenue()
    {
        $orders = CacheService::instance()->remember('orders', 60, function (){
            return Order::completed()->with(['invoice.salesPayments'])->get();
        }, true);

        $revenue = 0;

        foreach ($orders as $order) {
            $revenue += $order->invoice->total_paid;
        }

        return number_format($revenue, currency_decimals(), '.', ',') . main_currency_iso_code();
    }
}
