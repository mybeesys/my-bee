<?php

namespace App\Filament\Admin\Resources\PlatformCouponResource\Pages;

use App\Filament\Admin\Resources\PlatformCouponResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformCoupon extends EditRecord
{
    protected static string $resource = PlatformCouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
