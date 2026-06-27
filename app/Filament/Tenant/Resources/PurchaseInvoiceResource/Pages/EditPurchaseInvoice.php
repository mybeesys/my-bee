<?php

namespace App\Filament\Tenant\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Tenant\Concerns\HandlesInvoiceCreditPayments;
use App\Filament\Tenant\Resources\PurchaseInvoiceResource;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxProfile;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditPurchaseInvoice extends EditRecord
{
    use HandlesInvoiceCreditPayments;

    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getActions(): array
    {
        return [
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            PurchaseInvoiceResource::purchaseReturnInvoiceHeaderAction($this->record),
            ...parent::getHeaderActions(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareInvoiceFormDataForPersistence($data);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['discount_option_overall'] = $this->record->discount_option == 'overall';

        $items = [];

        foreach ($this->record->items as $invoiceItem) {
            $row = [
                'id' => $invoiceItem->id,
                'tenant_id' => $invoiceItem->tenant_id,
                'item_id' => $invoiceItem->product_variant_id ?? $invoiceItem->product_id,
                'item_type' => $invoiceItem->product_variant_id ? ProductVariant::class : Product::class,
                'type' => $invoiceItem->product_variant_id ? Product::$TYPE_VARIANTS : Product::$TYPE_BASIC,
                'product_id' => $invoiceItem->product_id,
                'product_variant_id' => $invoiceItem->product_variant_id,
                'name' => $invoiceItem->name,
                'qty' => $invoiceItem->qty,
                'price' => number_format($invoiceItem->price, currency_decimals(), '.', ''),
                'discount' => number_format($invoiceItem->discount, currency_decimals(), '.', ''),
                'tax' => number_format($invoiceItem->tax, currency_decimals(), '.', ''),
                'tax_profile_id' => $invoiceItem->tax_profile_id,
                'tax_profile_data' => TaxProfile::find($invoiceItem->tax_profile_id)?->toArray(),
                'sub_total' => format_amount($invoiceItem->qty * $invoiceItem->price),
            ];

            $items[Str::uuid()->toString()] = PurchaseInvoiceResource::hydrateInlineProductRow($row);
        }

        $data['items'] = $items;

        return parent::mutateFormDataBeforeFill($data);
    }

    protected function getFormActions(): array
    {
        if ($this->record->locked_at !== null) {
            fns()->sendWarning(__('fields.invoice_locked_statement'));

            return [];
        }

        return parent::getFormActions();
    }

    protected function afterSave(): void
    {
        foreach ($this->record->items as $item) {
            $item->delete();
        }

        $this->saveItems($this->record->id, $this->data);
        $this->processPendingCreditPayment();
    }

    protected function saveItems($invoice_id, $data): void
    {
        foreach ($data['items'] as $detail) {
            $detail = PurchaseInvoiceResource::normalizeInlineProductRowForSave($detail);

            if (empty($detail['product_id'])) {
                continue;
            }

            $taxProfile = TaxProfile::with('taxes')->find($detail['tax_profile_id']);

            InvoiceItem::create([
                'user_id' => auth()->id(),
                'tenant_id' => $detail['tenant_id'],
                'invoice_id' => $invoice_id,
                'name' => $detail['name'],
                'product_variant_id' => $detail['product_variant_id'],
                'product_id' => $detail['product_id'],
                'price' => $detail['price'],
                'qty' => $detail['qty'],
                'discount' => $detail['discount'],
                'tax' => $detail['tax'],
                'tax_profile_id' => $taxProfile?->id,
                'tax_profile_data' => $taxProfile?->toArray(),
            ]);
        }
    }
}
