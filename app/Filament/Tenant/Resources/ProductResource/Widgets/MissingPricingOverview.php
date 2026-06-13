<?php

    namespace App\Filament\Tenant\Resources\ProductResource\Widgets;

    use App\Models\ItemPrice;
    use App\Models\ItemStock;
    use App\Models\Product;
    use Filament\Widgets\StatsOverviewWidget as BaseWidget;
    use Filament\Widgets\StatsOverviewWidget\Card;

    class MissingPricingOverview extends BaseWidget
    {

        protected static ?string $pollingInterval = "60s";

        protected function getCards(): array
        {

            $missingPrices = Product::whereDoesntHave('lastPrice')->count();

            return [
                Card::make(__('fields.none_priced_items'), $missingPrices),
            ];
        }
    }
