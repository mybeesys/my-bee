<?php

namespace App\Filament\Tenant\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Tenant\Concerns\HandlesInvoiceCreditPayments;
use App\Filament\Tenant\Resources\PurchaseInvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SupplyOrder;
use App\Models\TaxProfile;
use App\Services\PricingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePurchaseInvoice extends CreateRecord
{
    use HandlesInvoiceCreditPayments;

    protected static string $resource = PurchaseInvoiceResource::class;

    protected $supply_order_id;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        parent::mount();

        if (purchases_invoices_maxed_out()) {
            fns()->sendInfo(__('fields.purchases_invoices_maxed_out_alert'));
            $this->redirect(PurchaseInvoiceResource::getUrl());
        }

        $this->supply_order_id = request('supply_order_id', null);

        $supplyOder = SupplyOrder::with('details.item')->find($this->supply_order_id);

        if ($supplyOder) {

            $items = [];

            foreach ($supplyOder->details as $detail) {

                $items[Str::uuid()->toString()] = [
                    'tenant_id' => $detail->tenant_id,
                    'product_id' => $detail->item_type == Product::class ? $detail->item_id : ProductVariant::find($detail->item_id)->product_id,
                    'product_variant_id' => $detail->item_type == ProductVariant::class ? $detail->item_id : null,
                    'item_id' => $detail->item_id,
                    'item_type' => $detail->item_type,
                    'name' => $detail->item->name,
                    'qty' => $detail->qty,
                    'price' => null,
                    'tax' => PricingService::instance()->getTaxAmount($detail->item instanceof Product ? $detail->item : $detail->item->product, $detail->unit_price, $detail->qty),
                    'discount' => 0,
                    'tax_profile_id' => null,
                    'tax_profile_data' => null,
                ];
            }

            foreach ($items as $key => $item) {
                $items[$key] = PurchaseInvoiceResource::hydrateInlineProductRow($item);
            }

            $this->form->fill([
                'no' => generate_invoice_no(),
                'date' => now(),
                'type' => 'purchases',
                'for' => 'supplier',
                'status' => 'confirmed',
                'discount_option' => 'per-item',
                'supply_order_id' => $supplyOder->id,
                'supplier_id' => $supplyOder->supplier_id,
                'items' => $items,
            ]);

            $this->data['items'] = $items;
            $this->cachedInvoiceLineItems = $items;

            PurchaseInvoiceResource::updateInvoicePropertiesFromLivewire($this);
        }

        if (empty(PurchaseInvoiceResource::inlineProductLinesFromState($this->data['items'] ?? []))) {
            PurchaseInvoiceResource::ensureDefaultInvoiceLineOnCreate($this, 'items');
            PurchaseInvoiceResource::updateInvoicePropertiesFromLivewire($this);
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->prepareInvoiceFormDataForPersistence($data);

        if (Invoice::firstWhere('no', $this->data['no'])) {
            $data['no'] = generate_invoice_no();
        }

        $data['status'] = 'confirmed';

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            DB::beginTransaction();

            $this->saveItems($this->record->id, $this->data);
            $this->record->refresh();
            $this->record->load(['items', 'additionalCosts.type', 'additionalCosts.taxProfile']);
            $this->record->confirmPurchaseInvoice();

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            $invoiceId = $this->record->id;
            InvoiceItem::where('invoice_id', $invoiceId)->delete();
            $this->record->additionalCosts()->delete();
            $this->record->delete();

            report($exception);

            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            throw new Halt();
        }

        $this->record->refresh();
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
