<?php

namespace App\Filament\Tenant\Resources\ProductsMovementResource\Pages;

use App\Filament\Tenant\Concerns\InitializesReportDateFilters;
use App\Filament\Tenant\Resources\ProductsMovementResource;
use App\Services\ProductMovementBalanceService;
use App\Services\ProductsMovementService;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ListProductsMovements extends ListRecords
{
    use InitializesReportDateFilters;

    protected static string $resource = ProductsMovementResource::class;

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $movementLines = null;

  /** @return array<int, array<string, mixed>> */
    protected function movementLines(): array
    {
        if ($this->movementLines === null) {
            $this->movementLines = app(ProductsMovementService::class)->build(
                $this->tableFilters['created_at'] ?? []
            );
        }

        return $this->movementLines;
    }

    public function getTableRecords(): EloquentCollection | Paginator | CursorPaginator
    {
        if ($this->cachedTableRecords !== null) {
            return $this->cachedTableRecords;
        }

        $records = app(ProductsMovementService::class)->toRecords($this->movementLines());
        $perPage = $this->getTableRecordsPerPage();

        if ($perPage === 'all') {
            app(ProductMovementBalanceService::class)->preloadForMovementLines($records);

            return $this->cachedTableRecords = $records;
        }

        $page = $this->getTablePage();
        $pageItems = $records->forPage($page, (int) $perPage)->values();

        app(ProductMovementBalanceService::class)->preloadForMovementLines($pageItems);

        return $this->cachedTableRecords = new LengthAwarePaginator(
            $pageItems,
            $records->count(),
            (int) $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $this->getTablePaginationPageName(),
            ]
        );
    }

    public function getAllTableRecordsCount(): int
    {
        return count($this->movementLines());
    }

    public function updatedTableFilters(): void
    {
        $this->movementLines = null;
        $this->resetTableRecordsCache();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
