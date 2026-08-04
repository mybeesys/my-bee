<?php

namespace App\Filament\Tenant\Resources\SupplyOrderResource\Pages;

use App\Filament\Tenant\Concerns\BlocksCreateWhenSubscriptionMaxed;
use App\Filament\Tenant\Resources\SupplyOrderResource;
use App\Models\SupplyOrderDetails;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplyOrder extends CreateRecord
{
    use BlocksCreateWhenSubscriptionMaxed;

    protected static string $resource = SupplyOrderResource::class;

    protected static function subscriptionLimitType(): string
    {
        return 'supply_orders';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        parent::mount();

        $this->abortCreateWhenSubscriptionMaxed(SupplyOrderResource::getUrl());

        if (empty(SupplyOrderResource::inlineProductLinesFromState($this->data['details'] ?? []))) {
            SupplyOrderResource::ensureDefaultInvoiceLineOnCreate($this, 'details');
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['details']);

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function afterCreate(): void
    {
        $this->saveItems($this->record->id, $this->data);
    }

    protected function saveItems($supply_order_id, $data): void
    {
        foreach ($data['details'] as $detail) {
            $detail = SupplyOrderResource::normalizeInlineProductRowForSave($detail);

            if (empty($detail['item_id'])) {
                continue;
            }

            SupplyOrderDetails::create([
                'tenant_id' => $detail['tenant_id'],
                'supply_order_id' => $supply_order_id,
                'user_id' => auth()->id(),
                'item_id' => $detail['item_id'],
                'item_type' => $detail['item_type'],
                'qty' => $detail['qty'],
            ]);
        }
    }
}
