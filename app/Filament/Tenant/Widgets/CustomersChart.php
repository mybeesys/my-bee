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

    protected function getType(): string
    {
        return 'line';
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
                ],
                [
                    'label' => __('fields.orders'),
                    'data' => $this->getOrders(),
                ],
                [
                    'label' => __('fields.purchases_invoices'),
                    'data' => $this->getPurchasesInvoices(),
                ],
                [
                    'label' => __('fields.sales_invoices'),
                    'data' => $this->getSalesInvoices(),
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

    public function getPurchasesInvoices()
    {
        $items = Invoice::purchases()->whereYear('created_at', $this->filter ?? now()->format('Y'))
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

    public function getSalesInvoices()
    {
        $items = Invoice::sales()->whereYear('created_at', $this->filter ?? now()->format('Y'))
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
