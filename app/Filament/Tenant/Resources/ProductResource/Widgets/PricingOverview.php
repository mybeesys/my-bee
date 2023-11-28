<?php

namespace App\Filament\Tenant\Resources\ProductResource\Widgets;

use App\Models\Currency;
use App\Models\ItemPrice;
use App\Models\ItemStock;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PricingOverview extends BaseWidget
{

    protected static ?string $pollingInterval = "60s";

    protected function getCards(): array
    {

        $cards = [];

        $missingPrices = Product::whereDoesntHave('lastPrice')->count();

        if ($missingPrices > 0) {
            $cards[] = Stat::make(__('fields.none_priced_items'), $missingPrices)
                ->extraAttributes([
//                        'class' => 'cursor-pointer',
//                        'wire:click' => '$emitUp("viewNonePricedItems", "processed")',
                ]);
        }

        foreach (ItemStock::where('item_type', Product::class)->get()->pluck('currency_iso_code')->unique()->toArray() as $currency_iso_code)
        {
            $allStocksCost = 0;
            foreach (ItemStock::where('item_type', Product::class)->get() as $stock) {
                $allStocksCost += $stock->getTotalCost($currency_iso_code);
            }

            $cards[] = Stat::make(__('fields.total_items_cost_in_warehouses'), format_amount($allStocksCost) . " $currency_iso_code")
                ->description(numbers_to_words($allStocksCost));

        }


        return $cards;
    }
}
