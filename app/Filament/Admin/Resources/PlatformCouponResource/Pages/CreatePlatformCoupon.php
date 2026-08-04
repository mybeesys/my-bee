<?php

namespace App\Filament\Admin\Resources\PlatformCouponResource\Pages;

use App\Filament\Admin\Resources\PlatformCouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformCoupon extends CreateRecord
{
    protected static string $resource = PlatformCouponResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
