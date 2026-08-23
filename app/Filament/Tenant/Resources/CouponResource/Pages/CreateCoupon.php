<?php

namespace App\Filament\Tenant\Resources\CouponResource\Pages;

use App\Filament\Tenant\Resources\CouponResource;
use App\Services\CouponService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return CouponService::instance()->create(
            $data,
            (int) filament()->getTenant()->id,
        );
    }
}
