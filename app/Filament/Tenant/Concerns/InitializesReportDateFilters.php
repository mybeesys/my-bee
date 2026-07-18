<?php

namespace App\Filament\Tenant\Concerns;

trait InitializesReportDateFilters
{
    protected function initializeReportDateFilters(string $filterName = 'created_at'): void
    {
        $current = $this->tableFilters[$filterName] ?? [];

        if (filled($current['created_from'] ?? null) || filled($current['created_until'] ?? null)) {
            return;
        }

        $this->tableFilters[$filterName] = array_merge($current, [
            'created_from' => now()->startOfYear()->toDateString(),
            'created_until' => now()->toDateString(),
        ]);
    }

    public function mount(): void
    {
        parent::mount();

        $this->initializeReportDateFilters();
    }
}
