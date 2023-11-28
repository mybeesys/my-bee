<?php

namespace App\Filament\Tenant\Resources\WarehouseResource\Pages;

use App\Filament\Tenant\Resources\WarehouseResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWarehouse extends EditRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\DeleteAction::make(),
        ];
    }
}
