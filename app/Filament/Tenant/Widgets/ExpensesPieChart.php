<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Widgets\ChartWidget;

class ExpensesPieChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 1;

    protected static ?string $pollingInterval = null;

    public function getHeading(): ?string
    {
        $total_expenses = Expense::all()->sum('sub_total');

        return __('fields.expenses') . ' (' . format_amount($total_expenses, includeCurrencyCode: true) . ')';
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
                        'padding' => 14,
                        'font' => ['size' => 11],
                    ],
                ],
            ],
            'maintainAspectRatio' => true,
        ];
    }

    protected function getData(): array
    {
        $data = [];
        $labels = [];
        $categories = ExpenseCategory::with('expenses')->get();
        $total_expenses = $categories->pluck('expenses')->flatten()->sum('sub_total');

        foreach ($categories as $category) {
            $expenses = $category->expenses->sum('sub_total');
            if ($expenses <= 0) {
                continue;
            }
            $labels[] = $category->name . ' (' . number_format(percent($expenses, $total_expenses), currency_decimals(), '.', '') . '%)';
            $data[] = number_format($expenses, currency_decimals(), '.', '');
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => __('fields.expenses'),
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(245, 158, 11, 0.85)',
                        'rgba(14, 165, 233, 0.85)',
                        'rgba(16, 185, 129, 0.85)',
                        'rgba(99, 102, 241, 0.85)',
                        'rgba(244, 63, 94, 0.85)',
                        'rgba(100, 116, 139, 0.85)',
                        'rgba(234, 179, 8, 0.85)',
                        'rgba(6, 182, 212, 0.85)',
                    ],
                    'borderWidth' => 0,
                    'hoverOffset' => 6,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
