<?php

namespace App\Filament\Tenant\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Tenant\Resources\PurchaseInvoiceResource;
use App\Filament\Tenant\Resources\PurchaseInvoiceStatusResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;


    protected function getActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('statuses')
                ->url(fn() => PurchaseInvoiceStatusResource::getUrl())
                ->color('gray')
                ->label(__('fields.purchase_status_types')),
        ];
    }
}
