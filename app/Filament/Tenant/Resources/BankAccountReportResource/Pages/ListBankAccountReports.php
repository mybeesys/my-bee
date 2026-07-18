<?php

namespace App\Filament\Tenant\Resources\BankAccountReportResource\Pages;

use App\Filament\Tenant\Concerns\InitializesReportDateFilters;
use App\Filament\Tenant\Resources\BankAccountReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankAccountReports extends ListRecords
{
    use InitializesReportDateFilters;

    protected static string $resource = BankAccountReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
