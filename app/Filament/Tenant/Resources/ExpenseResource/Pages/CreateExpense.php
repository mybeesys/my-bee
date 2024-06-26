<?php

namespace App\Filament\Tenant\Resources\ExpenseResource\Pages;

use App\Filament\Tenant\Resources\ExpenseResource;
use App\Models\ExpenseType;
use App\Services\AccountingService;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate()
    {
        $op = make_taxes_op();
        $accService = new AccountingService();
        $accService
            ->setUp(
                $op->id,
                now(),
                main_currency_iso_code(),
                generate_double_entry_transaction_id(),
                $this->record->tax,
                null,
                'Vat',
                'Vat',
                null,
                meta: ['type' => 'expense', 'id' => $this->record->id],
            )->make('120100001', '122800001')
            ->finish();
    }
}
