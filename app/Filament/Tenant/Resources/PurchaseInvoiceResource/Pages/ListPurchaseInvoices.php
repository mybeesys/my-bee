<?php

namespace App\Filament\Tenant\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\MyActions\Pages\AddToFavourites;
use App\Filament\Tenant\Resources\PurchaseInvoiceResource;
use App\Models\SupplyOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;

class ListPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected static string $view = 'filament.tenant.resources.pages.list-with-plan-limit';

    public string $subscriptionLimitType = 'purchase_invoices';

    protected function getActions(): array
    {
        return [
            AddToFavourites::make('fav')
                ->settingKey('fav.orders'),

            ActionGroup::make([

                CreateAction::make()
                    ->disabled(fn () => purchases_invoices_maxed_out()),

                Action::make('make_purchases_invoice_from_supply_order')
                    ->disabled(fn () => purchases_invoices_maxed_out())
                    ->label(__('fields.make_purchases_invoice_from_supply_order'))
                    ->requiresConfirmation()
                    ->color(Color::Sky)
                    ->form([
                        Select::make('supply_order_id')
                            ->required()
                            ->label(__('fields.supply_order'))
                            ->searchable()
                            ->options(function () {
                                $data = [];
                                foreach (SupplyOrder::all() as $item) {
                                    $data[$item->id] = $item->no . " - " . $item->description;
                                }

                                return $data;
                            }),
                    ])->action(function ($data) {
                        $this->redirect(PurchaseInvoiceResource::getUrl('create', ['supply_order_id' => $data['supply_order_id']]));
                    }),
            ])->button(),
        ];
    }
}
