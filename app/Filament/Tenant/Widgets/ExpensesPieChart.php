<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Widgets\ChartWidget;

class ExpensesPieChart extends ChartWidget
{

    protected static ?int $sort = 2;

    public function getHeading(): ?string
    {
        $total_expenses = Expense::all()->sum('amount');

        return __("fields.expenses") . " (" . format_amount($total_expenses) . ")";
    }

    protected function getData(): array
    {
        $data = [];
        $labels = [];
        $categories = ExpenseCategory::with('expenses')->get();
        $total_expenses = $categories->pluck('expenses')->flatten()->sum('amount');

        foreach ($categories as $category){
            $expenses = $category->expenses->sum('amount');
            $labels[] = $category->name . " (" . number_format(percent($expenses, $total_expenses), currency_decimals(), '.', '') . "%)";
            $data[] = number_format($expenses, currency_decimals(), '.', '');
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'my set',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 205, 86)',
                        'rgb(30, 100, 86)',
                        'rgb(60, 34, 200)',
                        'rgb(85, 80, 65)',
                        'rgb(95, 30, 53)',
                        'rgb(120, 17, 86)',
                        'rgb(140, 65, 46)',
                        'rgb(180, 55, 111)',
                    ],
                    'hoverOffset' => '4',
//                    'borderColor' => '#9BD0F5',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
