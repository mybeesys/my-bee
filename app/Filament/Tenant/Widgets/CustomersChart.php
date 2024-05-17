<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Customer;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Arr;

class CustomersChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): ?string
    {
        return __('fields.clients');
    }

    protected function getFilters(): ?array
    {
        $years = Customer::get()->groupBy(function ($item) {
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
                    'label' => __('fields.clients'),
                    'data' => $this->getClients(),
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    public function getClients()
    {
        $items = Customer::whereYear('created_at', $this->filter ?? now()->format('Y'))
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
