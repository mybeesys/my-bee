<?php

namespace App\Filament\Tenant\Resources\Acc3Resource\Pages;

use App\Filament\Tenant\Resources\Acc3Resource;
use Filament\Resources\Pages\ManageRecords;

class ManageAcc3s extends ManageRecords
{
    protected static string $resource = Acc3Resource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [];
    }
}
