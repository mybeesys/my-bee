<?php

namespace App\Filament\Tenant\Resources\OrderResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Resources\OrderResource;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = OrderResource::class;

    protected static string $view = 'filament.tenant.resources.orders.pages.list-orders';

    protected function getActions(): array
    {
        return [
            AddToFavourites::make('fav')
                ->settingKey('fav.orders'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return OrderResource::getWidgets();
    }
}
