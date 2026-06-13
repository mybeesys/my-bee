<?php

namespace App\Filament\Tenant\Resources\PurchasesReturnsResource\Pages;

use App\Filament\Tenant\Resources\PurchasesReturnsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchasesReturns extends EditRecord
{
    protected static string $resource = PurchasesReturnsResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
