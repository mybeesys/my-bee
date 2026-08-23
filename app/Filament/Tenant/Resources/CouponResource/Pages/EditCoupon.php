<?php

namespace App\Filament\Tenant\Resources\CouponResource\Pages;

use App\Filament\Tenant\Resources\CouponResource;
use App\Models\Coupon;
use App\Services\CouponService;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $type = $data['type'];
        if ($type === Coupon::$TYPE_FIXED) {
            $data['amount'] = $data['value'];
        }
        if ($type === Coupon::$TYPE_PERCENT) {
            $data['percent'] = $data['value'];
        }

        return parent::mutateFormDataBeforeFill($data);
    }

    protected function handleRecordUpdate($record, array $data): Coupon
    {
        return CouponService::instance()->update($record, $data);
    }
}
