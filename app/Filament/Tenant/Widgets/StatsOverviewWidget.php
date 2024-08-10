<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $startDate = filled($this->filters['startDate'] ?? null) ?
            Carbon::parse($this->filters['startDate']) :
            null;

        $endDate = filled($this->filters['endDate'] ?? null) ?
            Carbon::parse($this->filters['endDate']) :
            now();

        $isBusinessCustomersOnly = $this->filters['businessCustomersOnly'] ?? null;
        $businessCustomerMultiplier = match (true) {
            boolval($isBusinessCustomersOnly) => 2 / 3,
            blank($isBusinessCustomersOnly) => 1,
            default => 1 / 3,
        };

        $diffInDays = $startDate ? $startDate->diffInDays($endDate) : 0;

        $revenue = ($startDate ? ($diffInDays * 137) : 192100) * $businessCustomerMultiplier;
        $newCustomers = ($startDate ? ($diffInDays * 7) : 1340) * $businessCustomerMultiplier;
        $newOrders = ($startDate ? ($diffInDays * 13) : 3543) * $businessCustomerMultiplier;

        $formatNumber = function (int $number): string {
            if ($number < 1000) {
                return Number::format($number, 0);
            }

            if ($number < 1000000) {
                return Number::format($number / 1000, 2) . 'k';
            }

            return Number::format($number / 1000000, 2) . 'm';
        };

        return [
            Stat::make(__('fields.new_clients'), Customer::thisWeek()->count())
                ->description(__('fields.this_week'))
                ->color('success'),
            Stat::make(__('fields.new_orders'), Order::new()->thisWeek()->count())
                ->description(__('fields.this_week'))
                ->color('success'),
        ];
    }
}
