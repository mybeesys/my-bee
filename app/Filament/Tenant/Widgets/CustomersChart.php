<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Arr;

class CustomersChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 1;

    protected static ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 16,
                        'font' => ['size' => 11],
                    ],
                ],
            ],
            'elements' => [
                'line' => [
                    'tension' => 0.35,
                    'borderWidth' => 2,
                ],
                'point' => [
                    'radius' => 0,
                    'hitRadius' => 8,
                    'hoverRadius' => 4,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.15)'],
                ],
            ],
            'maintainAspectRatio' => true,
        ];
    }

    protected function getMonthLabels(): array
    {
        return collect(range(1, 12))
            ->map(fn (int $month) => Carbon::createFromDate(2000, $month, 1)->translatedFormat('M'))
            ->all();
    }

    public function getHeading(): ?string
    {
        return __('fields.clients_and_orders_chart');
    }

    protected function getFilters(): ?array
    {
        $customer_years = Customer::get()->groupBy(function ($item) {
            return $item->created_at->format('Y');
        })->toArray();

        $order_years = Order::get()->groupBy(function ($item) {
            return $item->created_at->format('Y');
        })->toArray();

        $invoices_years = Invoice::get()->groupBy(function ($item) {
            return $item->created_at->format('Y');
        })->toArray();

        $years = array_keys($customer_years);
        $years = array_merge($years, array_keys($order_years));
        $years = array_merge($years, array_keys($invoices_years));

        $years = collect($years)->unique()->toArray();

        $data = [];
        foreach ($years as $year) {
            $data[$year] = $year;
        }

        return collect($data)->reverse()->toArray();
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => __('fields.clients'),
                    'data' => $this->getClients(),
                    'borderColor' => 'rgb(14, 165, 233)',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.08)',
                    'fill' => true,
                ],
                [
                    'label' => __('fields.orders'),
                    'data' => $this->getOrders(),
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.08)',
                    'fill' => true,
                ],
                [
                    'label' => __('fields.purchases_invoices'),
                    'data' => $this->getPurchasesInvoices(),
                    'borderColor' => 'rgb(100, 116, 139)',
                    'backgroundColor' => 'rgba(100, 116, 139, 0.06)',
                    'fill' => true,
                ],
                [
                    'label' => __('fields.sales_invoices'),
                    'data' => $this->getSalesInvoices(),
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.08)',
                    'fill' => true,
                ],
            ],
            'labels' => $this->getMonthLabels(),
        ];
    }

    public function getClients()
    {
        $items = Customer::whereYear('created_at', $this->filter ?? now()->format('Y'))
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->translatedFormat('M');
            });

        $data = [];
        foreach ($this->getMonthLabels() as $value) {
            if (array_key_exists($value, $items->toArray())) {
                $data[] = $items[$value]->count();
            } else {
                $data[] = 0;
            }
        }

        return $data;
    }

    public function getOrders()
    {
        $items = Order::whereYear('created_at', $this->filter ?? now()->format('Y'))
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->translatedFormat('M');
            });

        $data = [];
        foreach ($this->getMonthLabels() as $value) {
            if (array_key_exists($value, $items->toArray())) {
                $data[] = $items[$value]->count();
            } else {
                $data[] = 0;
            }
        }

        return $data;
    }

    public function getPurchasesInvoices()
    {
        $items = Invoice::purchases()->whereYear('created_at', $this->filter ?? now()->format('Y'))
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->translatedFormat('M');
            });

        $data = [];
        foreach ($this->getMonthLabels() as $value) {
            if (array_key_exists($value, $items->toArray())) {
                $data[] = $items[$value]->count();
            } else {
                $data[] = 0;
            }
        }

        return $data;
    }

    public function getSalesInvoices()
    {
        $items = Invoice::sales()->whereYear('created_at', $this->filter ?? now()->format('Y'))
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->translatedFormat('M');
            });

        $data = [];
        foreach ($this->getMonthLabels() as $value) {
            if (array_key_exists($value, $items->toArray())) {
                $data[] = $items[$value]->count();
            } else {
                $data[] = 0;
            }
        }

        return $data;
    }

}
