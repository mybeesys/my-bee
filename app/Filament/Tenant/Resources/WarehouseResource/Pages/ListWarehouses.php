<?php

namespace App\Filament\Tenant\Resources\WarehouseResource\Pages;

    use App\Filament\Tenant\Resources\ProductResource\Widgets\PricingOverview;
    use App\Filament\Tenant\Resources\WarehouseResource;
    use App\Models\User;
    use Filament\Pages\Actions;
    use Filament\Resources\Pages\ListRecords;

    class ListWarehouses extends ListRecords
    {
        protected static string $resource = WarehouseResource::class;

        protected static string $view = 'filament.resources.warehouse-resource.pages.index';


        protected function getHeaderWidgets(): array
        {
            return [
                PricingOverview::class,
            ];
        }

        protected function getActions(): array
        {
            return [
                Actions\CreateAction::make(),
            ];
        }
    }
