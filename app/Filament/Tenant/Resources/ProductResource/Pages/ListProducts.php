<?php

namespace App\Filament\Tenant\Resources\ProductResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Pages\EditTenantProfile;
use App\Filament\Tenant\Resources\ProductResource;
use App\Filament\Tenant\Resources\ProductResource\Widgets\PricingOverview;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
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
            AddToFavourites::make('fav')
                ->settingKey('fav.products'),

//            Action::make('shop_settings')
//                ->label(__('fields.shop_settings'))
//                ->color('gray')
//                ->url(function () {
//                    $slug = filament()->getTenant()->slug;
//                    return "/$slug/profile";
//                }),

            CreateAction::make()
        ];
    }
}
