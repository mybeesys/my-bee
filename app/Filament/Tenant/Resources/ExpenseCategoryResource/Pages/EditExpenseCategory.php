<?php

namespace App\Filament\Tenant\Resources\ExpenseCategoryResource\Pages;

use App\Filament\Tenant\Resources\ExpenseCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditExpenseCategory extends EditRecord
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
