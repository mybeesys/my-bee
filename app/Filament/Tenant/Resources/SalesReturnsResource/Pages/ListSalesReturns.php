<?php

namespace App\Filament\Tenant\Resources\SalesReturnsResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Resources\SalesReturnsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesReturns extends ListRecords
{
    protected static string $resource = SalesReturnsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AddToFavourites::make('fav')
                ->settingKey('fav.sales_returns'),
            Actions\CreateAction::make(),
        ];
    }
}
