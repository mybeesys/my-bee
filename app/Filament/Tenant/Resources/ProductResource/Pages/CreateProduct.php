<?php

namespace App\Filament\Tenant\Resources\ProductResource\Pages;

use App\Filament\Tenant\Resources\ProductResource;
use App\Models\ItemPrice;
use App\Services\PricingService;
use App\Services\StockService;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use function Filament\Support\is_app_url;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        $this->handlePricing($this->record);
    }

    protected function handlePricing($record): void
    {
        $record->refresh();

        //basic pricing
        if($record->variants->isEmpty()){
            PricingService::instance()->addPrice($record, null, $this->data['price'] ?? null, $this->data['discount_price'] ?? null);
        }

        foreach ($record->variants as $productVariant) {
            $itemInData = collect($this->data['variants'])->firstWhere('sku', $productVariant->sku);

            if($itemInData)
            {
                $itemPrice = PricingService::instance()->addPrice($productVariant, null, $itemInData['price'] ?? null, $itemInData['discount_price'] ?? null);
            }
        }

        foreach ($record->extras as $productExtra) {
            $itemInData = collect($this->data['extras_table'])->firstWhere('item_extra_id', $productExtra->item_extra_id);

            if($itemInData)
            {
                $itemPrice = PricingService::instance()->addPrice($productExtra, null, $itemInData['price'] ?? null, $itemInData['discount_price']);
            }
        }
    }
}
