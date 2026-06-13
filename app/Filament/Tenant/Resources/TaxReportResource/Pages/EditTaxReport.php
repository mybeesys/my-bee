<?php

namespace App\Filament\Tenant\Resources\TaxReportResource\Pages;

use App\Filament\Tenant\Resources\TaxReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaxReport extends EditRecord
{
    protected static string $resource = TaxReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
