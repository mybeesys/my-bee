<?php

namespace App\Filament\Tenant\Resources\Acc4Resource\Pages;

use App\Filament\Tenant\Resources\Acc4Resource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAcc4s extends ManageRecords
{
    protected static string $resource = Acc4Resource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [];
    }
}
