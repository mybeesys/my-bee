<?php

namespace App\Filament\Tenant\Resources\ReceiptVoucherResource\Pages;

use App\Filament\Tenant\Resources\ReceiptVoucherResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReceiptVouchers extends ListRecords
{
    protected static string $resource = ReceiptVoucherResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}
