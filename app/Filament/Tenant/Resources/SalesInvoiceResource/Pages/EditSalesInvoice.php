<?php

namespace App\Filament\Tenant\Resources\SalesInvoiceResource\Pages;

use App\Filament\Tenant\Concerns\HandlesInvoiceCreditPayments;
use App\Filament\Tenant\Resources\SalesInvoiceResource;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Models\TaxProfile;
use App\Services\PricingService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditSalesInvoice extends EditRecord
{
    use HandlesInvoiceCreditPayments;

    protected static string $resource = SalesInvoiceResource::class;

    protected function getFormActions(): array
    {
        if ($this->record->isLocked()) {
            fns()->sendWarning(__('fields.invoice_locked_statement'));
            return [];
        }

        return parent::getFormActions();
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label(__('fields.save_sales_invoice'));
    }

    protected function beforeSave(): void
    {
        SalesInvoiceResource::updateInvoicePropertiesFromLivewire($this);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->prepareInvoiceFormDataForPersistence($data);

        if ($this->record->status !== 'cancelled') {
            $data['status'] = 'confirmed';
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['discount_option_overall'] = $this->record->discount_option == "overall";

        $items = [];
        foreach ($this->record->items as $invoiceItem) {
            $row = [
                'id' => $invoiceItem->id,
                "tenant_id" => $invoiceItem->tenant_id,
                "user_id" => $invoiceItem->user_id,
                "max_qty" => 1000,
                "item_id" => $invoiceItem->item_id,
                "item_type" => $invoiceItem->item_type,
                "type" => $invoiceItem->product_variant_id ? Product::$TYPE_VARIANTS : Product::$TYPE_BASIC,
                "unit_price" => number_format($invoiceItem->price, currency_decimals(), '.', ''),
                "discount" => number_format($invoiceItem->discount, currency_decimals(), '.', ''),
                "product_id" => $invoiceItem->product_id,
                'product_variant_id' => $invoiceItem->product_variant_id,
                'product_extras_ids' => $invoiceItem->extras->pluck('product_extra_id')->toArray(),
                'available_product_extras_ids' => $invoiceItem->product->extras->pluck('id')->toArray(),
                'extras_total' => PricingService::instance()->getRetailPrices(ProductExtra::with('lastPrice')->findMany($invoiceItem->extras->pluck('product_extra_id')->toArray())),
                "extras" => implode(', ', $invoiceItem->extras->pluck('display_name')->toArray()),
                "qty" => $invoiceItem->qty,
                'name' => $invoiceItem->name,
                "price" => number_format($invoiceItem->price, currency_decimals(), '.', ''),
                "tax" => number_format($invoiceItem->tax, currency_decimals(), '.', ''),
                'tax_profile_id' => $invoiceItem->tax_profile_id,
                'tax_profile_data' => TaxProfile::find($invoiceItem->tax_profile_id)?->toArray(),
                "sub_total" => number_format($invoiceItem->qty * $invoiceItem->price, currency_decimals(), '.', ''),
            ];

            $items[Str::uuid()->toString()] = SalesInvoiceResource::hydrateInlineProductRow($row);
        }

        $data['items'] = $items;
        return parent::mutateFormDataBeforeFill($data);
    }

    protected function afterSave(): void
    {
        $existingItems = $this->record->items()->with('extras')->get()->keyBy('id');

        foreach ($existingItems as $item) {
            $item->extras()->delete();
            $item->delete();
        }

        $this->saveItems($this->resolveLineItemsForSave(), $existingItems);

        $this->record->load('additionalCosts');

        foreach ($this->record->additionalCosts as $additionalCost) {
            if (($additionalCost->meta['type'] ?? null) === 'delivery_fees') {
                if ($this->record->order) {
                    $this->record->order->update(['delivery' => $additionalCost->cost]);
                }
            }
        }

        $this->processPendingCreditPayment();

        $this->record->refresh()->load('items');

        if ($this->record->isEditable()) {
            $this->record->confirmSalesInvoice();
            $this->record->refresh();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function resolveLineItemsForSave(): array
    {
        if (! empty($this->cachedInvoiceLineItems)) {
            return SalesInvoiceResource::inlineProductLinesFromState($this->cachedInvoiceLineItems);
        }

        return SalesInvoiceResource::inlineProductLinesFromState($this->data['items'] ?? []);
    }

    protected function saveItems(array $items, $existingItems): void
    {
        foreach ($items as $invoiceItem) {
            $invoiceItem = SalesInvoiceResource::normalizeInlineProductRowForSave($invoiceItem);

            if (empty($invoiceItem['product_id'])) {
                continue;
            }

            $existingItem = isset($invoiceItem['id']) ? $existingItems->get($invoiceItem['id']) : null;
            $taxProfile = TaxProfile::with('taxes')->find($invoiceItem['tax_profile_id'] ?? null);

            $name = $invoiceItem['name']
                ?? $existingItem?->name
                ?? Product::find($invoiceItem['product_id'])?->name;

            $ii = InvoiceItem::create([
                'user_id' => auth()->id(),
                'tenant_id' => $invoiceItem['tenant_id'] ?? filament()->getTenant()->id,
                'invoice_id' => $this->record->id,
                'order_details_id' => $existingItem?->order_details_id,
                'name' => $name,
                'product_variant_id' => $invoiceItem['product_variant_id'],
                'product_id' => $invoiceItem['product_id'],
                'price' => $invoiceItem['price'],
                'qty' => $invoiceItem['qty'],
                'discount' => $invoiceItem['discount'],
                'tax' => $invoiceItem['tax'],
                'tax_profile_id' => $taxProfile?->id,
                'tax_profile_data' => $taxProfile?->toArray(),
            ]);

            foreach ($invoiceItem['product_extras_ids'] ?? [] as $product_extra_id) {

                $productExtra = ProductExtra::with(['lastPrice', 'extra'])->findOrFail($product_extra_id);

                $ii->extras()->create([
                    'tenant_id' => $invoiceItem['tenant_id'],
                    'invoice_item_id' => $ii->id,
                    'product_extra_id' => $product_extra_id,
                    'unit_price' => PricingService::instance()->getRetailPrice($productExtra),
                    'display_name' => $productExtra->name,
                    'qty' => 1,
                ]);
            }
        }
    }
}
