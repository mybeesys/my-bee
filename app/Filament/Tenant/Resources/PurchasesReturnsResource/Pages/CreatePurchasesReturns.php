<?php

namespace App\Filament\Tenant\Resources\PurchasesReturnsResource\Pages;

use App\Filament\Tenant\Concerns\HandlesReturnCreditPayments;
use App\Filament\Tenant\Resources\PurchasesReturnsResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchasesReturns extends CreateRecord
{
    use HandlesReturnCreditPayments;

    protected static string $resource = PurchasesReturnsResource::class;

    public function mount(): void
    {
        parent::mount();

        $invoiceId = request()->integer('invoice_id');

        if ($invoiceId) {
            $invoice = Invoice::find($invoiceId);

            $this->form->fill([
                'return_mode' => 'invoice',
                'invoice_id' => $invoiceId,
                'prices_includes_taxes' => (bool) ($invoice?->prices_includes_taxes ?? true),
                'payment_terms' => $invoice?->payment_terms ?? 'cash',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->prepareReturnFormDataForPersistence($data);
        $data['user_id'] = filament()->auth()->id() ?? auth()->id();
        $returnMode = $this->data['return_mode'] ?? 'invoice';

        if ($returnMode === 'supplier') {
            $data['invoice_id'] = null;
        } else {
            $data['supplier_id'] = Invoice::find($data['invoice_id'])?->supplier_id;
        }

        return $data;
    }

    protected function beforeCreate(): void
    {
        $message = PurchasesReturnsResource::validateReturnDetailsForCreate($this->data);

        if ($message) {
            fns()->sendWarning($message);
            $this->halt();
        }

        if (($this->data['payment_terms'] ?? 'cash') === 'credit') {
            $returnTotal = PurchasesReturnsResource::sumReturnDetailsTotals($this->data['details'] ?? []);
            $refundAmount = $this->normalizeReturnCreditPaymentAmount(
                $this->returnCreditPaymentUiData()['credit_payment_amount'] ?? 0
            );

            if ($refundAmount > $returnTotal) {
                fns()->sendWarning(__('fields.payments_are_bigger_than_invoice_amount'));
                $this->halt();
            }
        }
    }

    public function afterCreate(): void
    {
        $returnMode = $this->data['return_mode'] ?? 'invoice';
        $returnTotal = PurchasesReturnsResource::syncExpandedReturnDetails($this->record, $this->data, $returnMode, 'purchases');

        PurchasesReturnsResource::settlePurchaseReturnPayment($this->record, $this->data, $returnTotal);
        $this->processPendingReturnCreditRefund('purchases', $returnTotal);

        foreach ($this->record->details as $detail) {
            $detail->update(['transaction_completed' => true]);
        }
    }
}
