<?php

namespace App\Filament\Tenant\Concerns;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

trait BuildsMonthlySparklineChart
{
    /**
     * @return array<int, int>
     */
    protected function monthlyCountChart(Builder $query): array
    {
        $start = now()->subMonths(6)->startOfMonth();
        $end = now()->endOfMonth();
        $table = $query->getModel()->getTable();

        $counts = (clone $query)
            ->whereBetween("{$table}.created_at", [$start, $end])
            ->selectRaw("DATE_FORMAT({$table}.created_at, '%Y-%m') as period, COUNT(*) as aggregate")
            ->groupByRaw("DATE_FORMAT({$table}.created_at, '%Y-%m')")
            ->pluck('aggregate', 'period');

        $data = [];

        foreach (CarbonPeriod::create($start, '1 month', $end) as $date) {
            $data[] = (int) ($counts[$date->format('Y-m')] ?? 0);
        }

        return $data;
    }
}
