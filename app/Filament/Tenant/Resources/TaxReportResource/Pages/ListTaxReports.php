<?php

namespace App\Filament\Tenant\Resources\TaxReportResource\Pages;

use App\Filament\Tenant\Resources\TaxReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxReports extends ListRecords
{
    protected static string $resource = TaxReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
