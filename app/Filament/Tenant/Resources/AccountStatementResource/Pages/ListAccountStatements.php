<?php

namespace App\Filament\Tenant\Resources\AccountStatementResource\Pages;

use App\Filament\Tenant\Concerns\InitializesReportDateFilters;
use App\Filament\Tenant\Resources\AccountStatementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListAccountStatements extends ListRecords
{
    use InitializesReportDateFilters;

    protected static string $resource = AccountStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        $msg = app()->getLocale() == "ar" ? "الرجاء إختيار حساب لإظهار السجلات" : "Select an account to view records";
        return new HtmlString("<span class='text-danger-600 text-xl font-bold'>$msg</span>");
    }
}
