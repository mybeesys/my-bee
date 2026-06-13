<?php

namespace App\Filament\Tenant\Resources\ProductResource\Widgets;

use App\Models\Currency;
use App\Models\ItemPrice;
use App\Models\ItemStock;
use App\Models\Product;
use App\Services\PricingService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;

class PricingOverview extends BaseWidget
{

    protected static ?string $pollingInterval = "60s";

    protected function getCards(): array
    {

        $cards = [];

        $products = Product::with(['prices', 'variants.prices', 'extras.prices'])->get();

        $missingBasicPricesCount = 0;
        $missingVariantsPricesCount = 0;
        $missingExtrasPricesCount = 0;

        $pricedNames = [];
        $missingNames = [];
        foreach ($products->where('type', Product::$TYPE_BASIC) as $item) {
            if (PricingService::instance()->getRetailPrice($item, 0) == 0){
                $missingBasicPricesCount++;
                $missingNames[] = $item->name;
            }else{
                $pricedNames[] = $item->name;
            }
        }

        foreach ($products->where('type', Product::$TYPE_VARIANTS) as $item) {
            foreach ($item->variants as $productVariant){
                if (PricingService::instance()->getRetailPrice($productVariant, 0) == 0){
                    $missingVariantsPricesCount++;
                    $missingNames[] = $productVariant->name;
                }else{
                    $pricedNames[] = $productVariant->name;
                }
            }
        }

        foreach ($products->pluck('extras')->flatten() as $productExtra) {
            if (PricingService::instance()->getRetailPrice($productExtra, 0) == 0){
                $missingExtrasPricesCount++;
                $missingNames[] = $item->name;
            }else{
                $pricedNames[] = $item->name;
            }
        }

        $cards[] = Stat::make(__('fields.none_priced_items'),
            $missingBasicPricesCount + $missingVariantsPricesCount + $missingExtrasPricesCount)
            ->description(implode(', ', []));

        return $cards;
    }
}
