<?php

namespace App\Filament\Tenant\Resources\OrderResource\Pages;

use App\Filament\Tenant\Resources\OrderResource;
use App\Filament\Tenant\Resources\ReceiptVoucherResource;
use App\Filament\Tenant\Resources\SalesInvoiceResource;
use App\Models\AdditionalCost;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ReceiptVoucher;
use App\Services\StockService;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getActions(): array
    {
        return [
            Actions\Action::make('change_status')
                ->label(__('fields.change_status'))
                ->icon('heroicon-o-pencil')
                ->color('warning')
                ->disabled(fn($record) => $record and $record->status === Order::$STATUS_CANCELLED or $record->status == Order::$STATUS_COMPLETED)
                ->modalWidth(MaxWidth::Small)
                ->form(function (Order $record) {
                    return [
                        Section::make()->schema([

                            Select::make('status')
                                ->label(__('fields.status'))
                                ->live()
                                ->options([
                                    Order::$STATUS_PACKAGING => __('fields.order_status_' . Order::$STATUS_PACKAGING),
                                    Order::$STATUS_DELIVERY_IN_PROGRESS => __('fields.order_status_' . Order::$STATUS_DELIVERY_IN_PROGRESS),
                                    Order::$STATUS_CANCELLED => __('fields.order_status_' . Order::$STATUS_CANCELLED),
                                    Order::$STATUS_COMPLETED => __('fields.order_status_' . Order::$STATUS_COMPLETED),
                                ])
                                ->default($record->status)
                                ->required(),

                            DatePicker::make('delivery_date')
                                ->label(__('fields.delivery_date'))
                                ->required()
                                ->default(today())
                                ->visible(fn(Get $get) => $get('status') === Order::$STATUS_COMPLETED),

                            DatePicker::make('canceled_date')
                                ->label(__('fields.canceled_date'))
                                ->required()
                                ->default(today())
                                ->visible(fn(Get $get) => $get('status') === Order::$STATUS_CANCELLED),

                            Textarea::make('canceled_reason')
                                ->label(__('fields.canceled_reason'))
                                ->visible(fn(Get $get) => $get('status') === Order::$STATUS_CANCELLED)
                                ->cols(5)
                                ->rows(5),

                            TextInput::make('delivery')
                                ->label(__('fields.delivery_price'))
                                ->visible(fn(Get $get) => $get('status') === Order::$STATUS_COMPLETED or $get('status') === Order::$STATUS_DELIVERY_IN_PROGRESS)
                                ->default($record->delivery)
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(PHP_INT_MAX)
                                ->formatStateUsing(fn($state) => is_number($state) ? number_format($state, currency_decimals(), '.', '') : null)
                                ->extraInputAttributes(['min' => 0, 'max' => PHP_INT_MAX]),


                            Placeholder::make('info')
                                ->visible(function (Get $get) {
                                    return ($get('status') === Order::$STATUS_COMPLETED or $get('status') === Order::$STATUS_CANCELLED);
                                })
                                ->label(function () {
                                    $msg = __("fields.order_will_be_locked_after_this_action");
                                    return new HtmlString("<strong style='color: #ff301d;'> $msg </strong>");
                                }),
                        ]),
                    ];
                })
                ->modalWidth(MaxWidth::Small)
                ->action(function (Order $record, array $data) {
                    try {
                        DB::beginTransaction();

                        if (array_key_exists('delivery', $data)) {
                            //sync additional cost
                            $invoice = $record->invoice;
                            $invAdditionalCost = AdditionalCost::where('meta->type', 'delivery_fees')->where('item_type', Invoice::class)->where('item_id', $invoice->id)->first();

                            $invAdditionalCost->update([
                                'cost' => $data['delivery'],
                            ]);

                        }

                        if ($data['status'] == Order::$STATUS_CANCELLED) {
                            //cancel invoice
                            $record->invoice->update([
                                'status' => 'cancelled',
                                'locked_by_id' => auth()->id(),
                                'locked_at' => now(),
                            ]);
                        }

                        if ($data['status'] == Order::$STATUS_COMPLETED) {
                            //confirmed invoice
                            $record->invoice->update([
                                'status' => 'confirmed',
                                'locked_by_id' => auth()->id(),
                                'locked_at' => now(),
                            ]);

                            StockService::instance()->takeStockFromSalesInvoice($record->invoice);

                        }

                        $record->update($data);

                        DB::commit();

                        fns()->saved();

                    } catch (\Throwable $exception) {
                        DB::rollBack();
                        report($exception);
                        fns()->displayException($exception);
                    }
                }),


            Actions\Action::make('view_invoice')
                ->label(__('fields.view_invoice'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn(Order $record) => SalesInvoiceResource::getUrl('edit', ['record' => $record->invoice->id]), true),

            Actions\Action::make('complete_payment')
                ->label(__('fields.payment_details'))
                ->icon('heroicon-o-currency-dollar')
                ->color('success')
                ->visible(function ($record) {
                    return !$record->invoice?->paid;
                })
                ->url(function (Order $record){
                    if ($record->invoice->salesPayments->isEmpty()) {
                        return ReceiptVoucherResource::getUrl('create', ['invoice_id' => $record->invoice->id, 'order_id' => $record->id]);
                    }

                    $rv = ReceiptVoucher::whereInvoiceId($record->id)->first();

                    if ($rv)
                        return ReceiptVoucherResource::getUrl('edit', ['record' => $rv->id]);
                }, true),
        ];
    }
}
