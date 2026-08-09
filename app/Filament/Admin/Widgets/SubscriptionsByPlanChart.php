<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Client;
use App\Models\Plan;
use Filament\Widgets\ChartWidget;

class SubscriptionsByPlanChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 1,
    ];

    protected static ?string $pollingInterval = null;

    /** @var array<int, string> */
    private const CHART_COLORS = [
        'rgb(251, 191, 36)',
        'rgb(56, 189, 248)',
        'rgb(52, 211, 153)',
        'rgb(167, 139, 250)',
        'rgb(251, 113, 133)',
        'rgb(148, 163, 184)',
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getHeading(): ?string
    {
        return __('fields.admin_dashboard_plans_chart');
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
                        'padding' => 12,
                        'boxWidth' => 8,
                        'font' => ['size' => 11],
                        'color' => 'rgb(100, 116, 139)',
                    ],
                ],
            ],
            'cutout' => '62%',
            'maintainAspectRatio' => true,
        ];
    }

    protected function getData(): array
    {
        $plans = Plan::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($plans as $index => $plan) {
            $count = Client::query()
                ->whereHas('subscription', fn ($query) => $query->where('plan_id', $plan->id))
                ->count();

            if ($count === 0) {
                continue;
            }

            $labels[] = (string) $plan->name;
            $data[] = $count;
            $colors[] = self::CHART_COLORS[$index % count(self::CHART_COLORS)];
        }

        if ($labels === []) {
            $labels = [__('fields.no_data')];
            $data = [0];
            $colors = ['rgb(203, 213, 225)'];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
