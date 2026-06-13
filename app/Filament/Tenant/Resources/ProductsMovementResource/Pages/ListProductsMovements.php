<?php

namespace App\Filament\Tenant\Resources\ProductsMovementResource\Pages;

use App\Filament\Tenant\Resources\ProductsMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductsMovements extends ListRecords
{
    protected static string $resource = ProductsMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
