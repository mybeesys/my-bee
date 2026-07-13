<?php

namespace App\Filament\Tenant\Resources\SalesReturnsResource\Pages;

use App\Filament\Tenant\Resources\SalesReturnsResource;
use App\Models\Invoice;
use App\Services\AccountingService;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesReturns extends CreateRecord
{
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
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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
    }

    public function afterCreate(): void
    {
        $customer = $this->record->resolveCustomer();

        if (! $customer?->acc4?->code) {
            fns()->sendWarning('customer account cannot be found');
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
                SalesReturnsResource::sumReturnDetailsTotals($this->data['details'] ?? []),
                null,
                'Return paid amount to customer - إرجاع المبلغ المدفوع للعميل',
                'Return paid amount to customer - إرجاع المبلغ المدفوع للعميل',
                $this->record->invoice_id,
                meta: ['type' => 'sales_returns', 'id' => $this->record->id],
            )->make('120100001', $customer->acc4->code)
            ->finish();

        foreach ($this->record->details as $detail) {
            $detail->update(['transaction_completed' => true]);
        }
    }
}
