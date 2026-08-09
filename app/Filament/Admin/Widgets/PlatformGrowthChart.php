<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Client;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class PlatformGrowthChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 2,
    ];

    protected static ?string $pollingInterval = null;

    /** @var array<string, string> */
    private const SERIES_COLORS = [
        'clients' => 'rgb(251, 191, 36)',
        'tenants' => 'rgb(56, 189, 248)',
        'subscriptions' => 'rgb(52, 211, 153)',
    ];

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): ?string
    {
        return __('fields.admin_dashboard_growth_chart');
    }

    protected function getFilters(): ?array
    {
        $years = collect([
            ...Client::query()->distinct()->pluck('created_at'),
            ...Tenant::query()->distinct()->pluck('created_at'),
            ...Subscription::query()->distinct()->pluck('created_at'),
        ])
            ->map(fn ($date) => Carbon::parse($date)->format('Y'))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if ($years === []) {
            $years = [now()->format('Y')];
        }

        return array_combine($years, $years);
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
                    'ticks' => ['color' => 'rgb(148, 163, 184)'],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.12)'],
                    'ticks' => [
                        'color' => 'rgb(148, 163, 184)',
                        'precision' => 0,
                    ],
                ],
            ],
            'maintainAspectRatio' => true,
        ];
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                $this->lineDataset(
                    __('fields.admin_dashboard_clients_series'),
                    $this->monthlyCounts(Client::query()),
                    self::SERIES_COLORS['clients'],
                ),
                $this->lineDataset(
                    __('fields.admin_dashboard_tenants_series'),
                    $this->monthlyCounts(Tenant::query()),
                    self::SERIES_COLORS['tenants'],
                ),
                $this->lineDataset(
                    __('fields.admin_dashboard_subscriptions_series'),
                    $this->monthlyCounts(Subscription::query()),
                    self::SERIES_COLORS['subscriptions'],
                ),
            ],
            'labels' => $this->getMonthLabels(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function getMonthLabels(): array
    {
        return collect(range(1, 12))
            ->map(fn (int $month) => Carbon::createFromDate(2000, $month, 1)->translatedFormat('M'))
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return array<int, int>
     */
    protected function monthlyCounts($query): array
    {
        $year = $this->filter ?? now()->format('Y');
        $table = $query->getModel()->getTable();

        $counts = (clone $query)
            ->whereYear("{$table}.created_at", $year)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as aggregate')
            ->groupByRaw('MONTH(created_at)')
            ->pluck('aggregate', 'month');

        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $data[] = (int) ($counts[$month] ?? 0);
        }

        return $data;
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
            'fill' => false,
        ];
    }
}
