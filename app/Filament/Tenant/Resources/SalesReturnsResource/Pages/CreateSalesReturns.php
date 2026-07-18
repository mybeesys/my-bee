<?php

namespace App\Filament\Tenant\Resources\SalesReturnsResource\Pages;

use App\Filament\Tenant\Concerns\HandlesReturnCreditPayments;
use App\Filament\Tenant\Resources\SalesReturnsResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesReturns extends CreateRecord
{
    use HandlesReturnCreditPayments;

    protected static string $resource = SalesReturnsResource::class;

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

        if ($returnMode === 'customer') {
            $data['invoice_id'] = null;
        } else {
            $data['customer_id'] = Invoice::find($data['invoice_id'])?->customer_id;
        }

        return $data;
    }

    protected function beforeCreate(): void
    {
        $message = SalesReturnsResource::validateReturnDetailsForCreate($this->data);

        if ($message) {
            fns()->sendWarning($message);
            $this->halt();
        }

        if (($this->data['payment_terms'] ?? 'cash') === 'credit') {
            $returnTotal = SalesReturnsResource::sumReturnDetailsTotals($this->data['details'] ?? []);
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
        $returnTotal = SalesReturnsResource::syncExpandedReturnDetails($this->record, $this->data, $returnMode, 'sales');

        SalesReturnsResource::settleSalesReturnPayment($this->record, $this->data, $returnTotal);
        $this->processPendingReturnCreditRefund('sales', $returnTotal);

        foreach ($this->record->details as $detail) {
            $detail->update(['transaction_completed' => true]);
        }
    }
}
