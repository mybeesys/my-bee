<?php

namespace App\Filament\Tenant\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Tenant\Resources\PurchaseInvoiceResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getActions(): array
    {
        return [
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->record->locked_at !== null) {
            fns()->sendWarning(__('fields.invoice_locked_statement'));
            return [];
        }

        return parent::getFormActions();
    }
}
