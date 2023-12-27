<?php

namespace App\Filament\Tenant\Resources\OrderResource\Pages;

use App\Filament\Tenant\Resources\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

//    protected function getHeaderWidgets(): array
//    {
//        return OrderResource::getWidgets();
//    }
}
