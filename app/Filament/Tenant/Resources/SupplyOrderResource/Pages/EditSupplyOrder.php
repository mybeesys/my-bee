<?php

namespace App\Filament\Tenant\Resources\SupplyOrderResource\Pages;

use App\Filament\Tenant\Resources\SupplyOrderResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SupplyOrderDetails;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditSupplyOrder extends EditRecord
{
    protected static string $resource = SupplyOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['details']);

        return parent::mutateFormDataBeforeSave($data);
    }

    protected function afterSave(): void
    {
        $this->record->details()->delete();
        $this->saveItems($this->data);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $details = [];

        foreach ($this->record->details as $detail) {
            $product_id = $detail->item instanceof Product
                ? $detail->item_id
                : $detail->item->product->id;

            $row = [
                'id' => $detail->id,
                'tenant_id' => $detail->tenant_id,
                'user_id' => $detail->user_id,
                'max_qty' => 100000,
                'item_id' => $detail->item_id,
                'item_type' => $detail->item_type,
                'type' => $detail->item instanceof ProductVariant ? Product::$TYPE_VARIANTS : Product::$TYPE_BASIC,
                'product_id' => $product_id,
                'product_variant_id' => $detail->item instanceof ProductVariant ? $detail->item_id : null,
                'qty' => $detail->qty,
                'display_name' => $detail->item->name,
            ];

            $details[Str::uuid()->toString()] = SupplyOrderResource::hydrateInlineProductRow($row);
        }

        $data['details'] = $details;

        return parent::mutateFormDataBeforeFill($data);
    }

    protected function saveItems($data): void
    {
        foreach ($data['details'] as $detail) {
            $detail = SupplyOrderResource::normalizeInlineProductRowForSave($detail);

            if (empty($detail['item_id'])) {
                continue;
            }

            SupplyOrderDetails::create([
                'tenant_id' => $detail['tenant_id'],
                'supply_order_id' => $this->record->id,
                'user_id' => auth()->id(),
                'item_id' => $detail['item_id'],
                'item_type' => $detail['item_type'],
                'qty' => $detail['qty'],
            ]);
        }
    }
}
