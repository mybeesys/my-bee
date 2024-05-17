<?php

namespace App\Filament\Tenant\Resources\TreasuryAccountReportResource\Pages;

use App\Filament\Tenant\Resources\TreasuryAccountReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTreasuryAccountReport extends EditRecord
{
    protected static string $resource = TreasuryAccountReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
