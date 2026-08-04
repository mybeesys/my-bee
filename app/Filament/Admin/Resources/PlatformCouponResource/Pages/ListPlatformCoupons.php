<?php

namespace App\Filament\Admin\Resources\PlatformCouponResource\Pages;

use App\Filament\Admin\Resources\PlatformCouponResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformCoupons extends ListRecords
{
    protected static string $resource = PlatformCouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
