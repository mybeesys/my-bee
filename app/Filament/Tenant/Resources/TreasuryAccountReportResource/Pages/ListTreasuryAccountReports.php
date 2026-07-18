<?php

namespace App\Filament\Tenant\Resources\TreasuryAccountReportResource\Pages;

use App\Filament\Tenant\Concerns\InitializesReportDateFilters;
use App\Filament\Tenant\Resources\TreasuryAccountReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTreasuryAccountReports extends ListRecords
{
    use InitializesReportDateFilters;

    protected static string $resource = TreasuryAccountReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
