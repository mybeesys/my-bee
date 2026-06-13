<?php

namespace App\Filament\Tenant\Resources\BankAccountReportResource\Pages;

use App\Filament\Tenant\Resources\BankAccountReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBankAccountReport extends EditRecord
{
    protected static string $resource = BankAccountReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
