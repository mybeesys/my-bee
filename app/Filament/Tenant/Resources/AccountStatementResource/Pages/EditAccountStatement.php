<?php

namespace App\Filament\Tenant\Resources\AccountStatementResource\Pages;

use App\Filament\Tenant\Resources\AccountStatementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccountStatement extends EditRecord
{
    protected static string $resource = AccountStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
