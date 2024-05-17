<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class OrdersChart extends ChartWidget
{
    protected static ?string $heading = 'Orders per month';

    protected static ?int $sort = 1;

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): ?string
    {
        return __('fields.orders');
    }

    protected function getFilters(): ?array
    {
        $years = Order::get()->groupBy(function ($item) {
            return $item->created_at->format('Y');
        })->toArray();

        foreach ($years as $key => $value) {
            $years[$key] = $key;
        }

        return collect($years)->reverse()->toArray();
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => __('fields.orders'),
                    'data' => $this->getOrders(),
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    public function getOrders()
    {
        $items = Order::whereYear('created_at', $this->filter ?? now()->format('Y'))
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->format('M'); // grouping by months
            });

        $data = [];
        foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $key => $value) {
            if (array_key_exists($value, $items->toArray())) {
                $data[] = $items[$value]->count();
            } else {
                $data[] = 0;
            }
        }

        return $data;
    }
}
