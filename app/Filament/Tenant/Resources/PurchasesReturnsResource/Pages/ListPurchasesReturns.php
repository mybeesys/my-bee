<?php

namespace App\Filament\Tenant\Resources\PurchasesReturnsResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Resources\PurchasesReturnsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchasesReturns extends ListRecords
{
    protected static string $resource = PurchasesReturnsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AddToFavourites::make('fav')
                ->settingKey('fav.purchases_returns'),
            Actions\CreateAction::make(),
        ];
    }
}
