<?php

namespace App\Filament\Tenant\Resources\SalesInvoiceResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Resources\SalesInvoiceResource;
use App\Models\PriceOffer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;

class ListSalesInvoices extends ListRecords
{
    protected static string $resource = SalesInvoiceResource::class;

    protected static string $view = 'filament.tenant.resources.sales-invoices.pages.list-sales-invoices';

    protected function getActions(): array
    {
        return [
            AddToFavourites::make('fav')
                ->settingKey('fav.sales_invoices'),

            ActionGroup::make([

                CreateAction::make()
                    ->disabled(fn () => sales_invoices_maxed_out()),

                Action::make('make_sales_invoice_from_price_offer')
                    ->disabled(fn () => sales_invoices_maxed_out())
                    ->label(__('fields.make_sales_invoice_from_price_offer'))
                    ->requiresConfirmation()
                    ->color(Color::Sky)
                    ->form([
                        Select::make('price_offer_id')
                            ->required()
                            ->label(__('fields.price_offers'))
                            ->searchable()
                            ->options(function () {
                                $data = [];
                                foreach (PriceOffer::all() as $priceOffer) {
                                    $data[$priceOffer->id] = $priceOffer->no . ' - ' . $priceOffer->description;
                                }

                                return $data;
                            }),
                    ])->action(function ($data) {
                        $this->redirect(SalesInvoiceResource::getUrl('create', ['price_offer_id' => $data['price_offer_id']]));
                    }),

            ])->button(),
        ];
    }
}
