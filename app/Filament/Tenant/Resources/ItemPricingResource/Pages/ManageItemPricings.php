<?php

namespace App\Filament\Tenant\Resources\ItemPricingResource\Pages;

use App\Filament\Tenant\Resources\ItemPricingResource;
use App\Filament\Tenant\Resources\ProductResource\Widgets\MissingPricingOverview;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageItemPricings extends ManageRecords
{
    protected static string $resource = ItemPricingResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            MissingPricingOverview::class,
        ];
    }

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make()->using(function (array $data, string $model): Model {
                unset($data['profit']);
                return $model::create($data);
            }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['last_pricing_date'] = "-";
        $data['last_pricing_sdg'] = "-";
        $data['last_pricing_usd'] = "-";
        $data['last_stock_unit_cost_sdg'] = "-";
        $data['last_stock_unit_cost_usd'] = "-";

        return $data;
    }


    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

}
