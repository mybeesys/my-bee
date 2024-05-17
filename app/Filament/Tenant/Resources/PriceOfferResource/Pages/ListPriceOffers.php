<?php

namespace App\Filament\Tenant\Resources\PriceOfferResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Resources\PriceOfferResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Rupadana\FilamentAnnounce\Announce;

class ListPriceOffers extends ListRecords
{
    protected static string $resource = PriceOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AddToFavourites::make('fav')
                ->settingKey('fav.sales_price_offer'),
            Actions\CreateAction::make(),
        ];
    }
}
