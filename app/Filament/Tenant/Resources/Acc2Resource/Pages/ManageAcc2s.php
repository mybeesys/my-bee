<?php

namespace App\Filament\Tenant\Resources\Acc2Resource\Pages;

use App\Filament\Tenant\Resources\Acc2Resource;
use Filament\Resources\Pages\ManageRecords;

class ManageAcc2s extends ManageRecords
{
    protected static string $resource = Acc2Resource::class;

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
