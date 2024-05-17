<?php

namespace App\Filament\Tenant\Resources\SalesInvoiceResource\Pages;

use App\Filament\Tenant\Resources\SalesInvoiceResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $order = Order::where('invoice_id', $this->record->id)->first();

        if ($order) {
            return __("fields.order_no") . " #". $order->no;
        }
        return null;
    }
}
