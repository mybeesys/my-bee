<?php

namespace App\Filament\Tenant\Resources\SupplyOrderResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Resources\SupplyOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupplyOrders extends ListRecords
{
    protected static string $resource = SupplyOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AddToFavourites::make('fav')
                ->settingKey('fav.supply_order'),
            Actions\CreateAction::make(),
        ];
    }
}
