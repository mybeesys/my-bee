<?php

namespace App\Filament\Tenant\Resources\UnitResource\Pages;

use App\Filament\Tenant\Resources\UnitResource;
use Filament\Actions\CreateAction;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageUnits extends ManageRecords
{
    protected static string $resource = UnitResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
