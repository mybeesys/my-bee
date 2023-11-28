<?php

    namespace App\Filament\Tenant\Resources\ProductResource\Pages;

    use App\Filament\Tenant\Resources\ProductResource;
    use App\Filament\Tenant\Resources\ProductResource\Widgets\PricingOverview;
    use App\Models\User;
    use Filament\Pages\Actions;
    use Filament\Resources\Pages\ListRecords;

    class ListProducts extends ListRecords
    {
        protected static string $resource = ProductResource::class;

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
