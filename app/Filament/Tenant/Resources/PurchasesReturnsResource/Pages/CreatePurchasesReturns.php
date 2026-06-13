<?php

namespace App\Filament\Tenant\Resources\PurchasesReturnsResource\Pages;

use App\Filament\Tenant\Resources\PurchasesReturnsResource;
use App\Models\Invoice;
use App\Services\AccountingService;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchasesReturns extends CreateRecord
{
    protected static string $resource = PurchasesReturnsResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeCreate(): void
    {
        $invoice = Invoice::find($this->data['invoice_id']);

        $total_paid_by_treasury_without_delivery_fees_or_other_fees = $invoice->getItemsCost(false, true, true);

        if ($invoice->status !== 'confirmed') {
            fns()->sendWarning(__('fields.you_need_to_confirm_invoice_before_this_operation'));
            $this->halt();
        }

        if (collect($this->data['details'])->sum('total') > $total_paid_by_treasury_without_delivery_fees_or_other_fees) {
            fns()->sendWarning(__('fields.to_be_returned_amount_is_greater_than_paid_amount'));
            $this->halt();
        }
    }
    public function afterCreate(): void
    {
        if (!$this->record->invoice->supplier->acc4->code) {
            fns()->sendWarning('supplier account cannot be found');
            $this->halt();
        }

        $op = make_general_voucher_op();
        $accService = new AccountingService();
        $accService
            ->setUp(
                $op->id,
                now(),
                main_currency_iso_code(),
                generate_double_entry_transaction_id(),
                collect($this->data['details'])->sum('total'),
                null,
                'Return paid amount to treasury - إرجاع المبلغ المدفوع للصندوق',
                'Return paid amount to treasury - إرجاع المبلغ المدفوع للصندوق',
                $this->record->invoice_id,
                meta: ['type' => 'purchases_returns', 'id' => $this->record->id],
            )->make($this->record->invoice->supplier->acc4->code, '120100001')
            ->finish();

        foreach ($this->record->details as $detail) {
            $detail->update(['transaction_completed' => true]);
        }
    }
}
