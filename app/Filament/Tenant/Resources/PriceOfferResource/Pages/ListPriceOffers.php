<?php

namespace App\Filament\Tenant\Resources\PriceOfferResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Resources\PriceOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPriceOffers extends ListRecords
{
    protected static string $resource = PriceOfferResource::class;

    protected static string $view = 'filament.tenant.resources.pages.list-with-plan-limit';

    public string $subscriptionLimitType = 'price_offers';

    protected function getHeaderActions(): array
    {
        return [
            AddToFavourites::make('fav')
                ->settingKey('fav.sales_price_offer'),
            Actions\CreateAction::make()
                ->disabled(fn () => price_offers_maxed_out()),
        ];
    }
}
