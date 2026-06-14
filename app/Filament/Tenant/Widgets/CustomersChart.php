<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class CustomersChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 1;

    protected static ?string $pollingInterval = null;

    /** @var array<string, string> */
    private const SERIES_COLORS = [
        'clients' => 'rgb(147, 197, 253)',
        'orders' => 'rgb(252, 211, 77)',
        'purchases' => 'rgb(203, 213, 225)',
        'sales' => 'rgb(134, 239, 172)',
    ];

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
                        'pointStyle' => 'circle',
                        'padding' => 16,
                        'boxWidth' => 8,
                        'boxHeight' => 8,
                        'font' => ['size' => 11],
                        'color' => 'rgb(100, 116, 139)',
                    ],
                ],
            ],
            'elements' => [
                'line' => [
                    'tension' => 0.35,
                    'borderWidth' => 1.5,
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
                    'ticks' => ['color' => 'rgb(148, 163, 184)'],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.12)'],
                    'ticks' => ['color' => 'rgb(148, 163, 184)'],
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
                $this->lineDataset(__('fields.clients'), $this->getClients(), self::SERIES_COLORS['clients']),
                $this->lineDataset(__('fields.orders'), $this->getOrders(), self::SERIES_COLORS['orders']),
                $this->lineDataset(__('fields.purchases_invoices'), $this->getPurchasesInvoices(), self::SERIES_COLORS['purchases']),
                $this->lineDataset(__('fields.sales_invoices'), $this->getSalesInvoices(), self::SERIES_COLORS['sales']),
            ],
            'labels' => $this->getMonthLabels(),
        ];
    }

    /**
     * @param  array<int, int|float>  $data
     * @return array<string, mixed>
     */
    private function lineDataset(string $label, array $data, string $color): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'borderColor' => $color,
            'backgroundColor' => $color,
            'pointBackgroundColor' => $color,
            'pointBorderColor' => $color,
            'pointBorderWidth' => 0,
            'fill' => false,
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
