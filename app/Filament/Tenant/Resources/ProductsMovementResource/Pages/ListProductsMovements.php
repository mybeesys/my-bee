<?php

namespace App\Filament\Tenant\Resources\ProductsMovementResource\Pages;

use App\Filament\Tenant\Resources\ProductsMovementResource;
use App\Services\ProductMovementBalanceService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class ListProductsMovements extends ListRecords
{
    protected static string $resource = ProductsMovementResource::class;

    protected function paginateTableQuery(Builder $query): Paginator | CursorPaginator
    {
        $paginator = parent::paginateTableQuery($query);

        app(ProductMovementBalanceService::class)->preloadForItems($paginator->getCollection());

        return $paginator;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
