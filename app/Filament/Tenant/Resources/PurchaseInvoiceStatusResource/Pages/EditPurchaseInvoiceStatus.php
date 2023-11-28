<?php

namespace App\Filament\Tenant\Resources\PurchaseInvoiceStatusResource\Pages;

use App\Filament\Tenant\Resources\PurchaseInvoiceStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoiceStatus extends EditRecord
{
    protected static string $resource = PurchaseInvoiceStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
