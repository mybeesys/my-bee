<?php

namespace App\Filament\Tenant\Resources\PaymentVoucherResource\Pages;

use App\Filament\Tenant\Resources\PaymentVoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentVouchers extends ListRecords
{
    protected static string $resource = PaymentVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
