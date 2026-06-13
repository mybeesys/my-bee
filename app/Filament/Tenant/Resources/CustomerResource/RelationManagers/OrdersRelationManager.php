<?php

namespace App\Filament\Tenant\Resources\CustomerResource\RelationManagers;

use App\Filament\Tenant\Resources\OrderResource;
use App\Filament\Tenant\Resources\ReceiptVoucherResource;
use App\Filament\Tenant\Resources\SalesInvoiceResource;
use App\Models\AdditionalCost;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ReceiptVoucher;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fields.orders');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('no')
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->label(__('fields.order_no'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('fields.client'))
                    ->searchable(),
//                    ->url(function (Order $record) {
//                        return CustomerRe::getUrl("edit", $record->client_id);
//                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('fields.status'))
                    ->badge()
//                    ->colors([
//                        'new' => 'gray',
//                        'packaging' => 'warning',
//                        'delivery-in-progress' => 'success',
//                        'completed' => Color::Green,
//                        'cancelled' => 'danger',
//                    ])
                    ->getStateUsing(fn($record) => __('fields.order_status_' . $record->status))
                    ->searchable(),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->label(__('fields.payment_status'))
                    ->getStateUsing(fn(Order $record) => $record->invoice?->payment_status),

                Tables\Columns\TextColumn::make('sub_total')
                    ->toggleable()
                    ->label(__('fields.sub_total'))
                    ->tooltip(function ($record) {
                        return format_amount($record->sub_total);
                    })
                    ->getStateUsing(function (Order $record) {
                        if ($record->discount > 0) {
                            $originalPrice = format_amount($record->sub_total + $record->discount) . " " . main_currency_iso_code();
                            $discountedPrice = format_amount($record->sub_total) . " " . main_currency_iso_code();
                            return new HtmlString("<p><h1 style='text-decoration: line-through; font-weight: lighter; color: #ff5028;'>$originalPrice</h1>  $discountedPrice</p>");
                        }
                        return main_currency_iso_code() . " " . format_amount($record->sub_total);
                    })->description(function (Order $record){
                        return $record['coupon_data']['code'] ?? null;
                    }),

                Tables\Columns\TextColumn::make('delivery')
                    ->toggleable()
                    ->label(__('fields.delivery_price'))
                    ->getStateUsing(function (Order $record) {
                        return main_currency_iso_code() . " " . format_amount($record->delivery);
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->toggleable()
                    ->label(__('fields.total'))
//                    ->tooltip('sub total + delivery price + additional delivery price - discount')
                    ->getStateUsing(function (Order $record) {
                        return main_currency_iso_code() . " " . format_amount($record->total);
                    }),

                Tables\Columns\TextColumn::make('delivery_type')
                    ->toggleable()
                    ->label(__('fields.delivery_type'))
                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('payment_method')
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->label(__('fields.payment_method'))
//                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('other_payment_method')
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->label(__('fields.other_payment_method'))
//                    ->searchable(),

                Tables\Columns\TextColumn::make('delivery_address')
                    ->toggleable()
                    ->label(__('fields.delivery_address'))
                    ->searchable(),

//                Tables\Columns\TextColumn::make('discount')
//                    ->toggleable()
//                    ->toggledHiddenByDefault()
//                    ->label(__('fields.discount'))
//                    ->searchable(),

//                Tables\Columns\TextColumn::make('delivery')
//                    ->toggleable()
//                    ->label(__('fields.delivery_price'))
//                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('delivery_extra')
//                    ->toggleable()
//                    ->label(__('fields.additional_delivery_price'))
//                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('fields.order_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.delivery_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_date')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.paid_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('canceled_date')
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->label(__('fields.canceled_date'))
                    ->dateTime('M j, Y')
                    ->sortable(),

            ])
            ->filters([

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('fields.status'))
                    ->multiple()
                    ->options([
                        Order::$STATUS_NEW => __('fields.order_status_' . Order::$STATUS_NEW),
                        Order::$STATUS_PACKAGING => __('fields.order_status_' . Order::$STATUS_PACKAGING),
                        Order::$STATUS_DELIVERY_IN_PROGRESS => __('fields.order_status_' . Order::$STATUS_DELIVERY_IN_PROGRESS),
                        Order::$STATUS_CANCELLED => __('fields.order_status_' . Order::$STATUS_CANCELLED),
                        Order::$STATUS_COMPLETED => __('fields.order_status_' . Order::$STATUS_COMPLETED),
                    ]),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label(__('fields.client'))
                    ->multiple()
                    ->options(Order::with('customer')->get()->pluck('customer.name', 'customer.id')),


                Tables\Filters\Filter::make('created_at')
                    ->label(__('fields.created_at'))
                    ->form([

                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('fields.created_from')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('fields.created_until')),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = null;
                        if ($data['created_from'] or $data['created_until']) {
                            $indicator = $indicator . __('fields.date');
                        }
                        return $indicator;
                    })
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'],
                                fn($query) => $query->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'],
                                fn($query) => $query->whereDate('created_at', '<=', $data['created_until']));
                    })

            ])
            ->actions([
                OrderResource::configureOrderTableActionGroup(Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    OrderResource::makeOrderChangeStatusTableAction(),
                    OrderResource::makeReviewSalesInvoiceTableAction(),
                    OrderResource::makeConfirmSalesInvoiceTableAction(),
                    OrderResource::makeCompletePaymentTableAction(),
                ])),
            ])
            ->bulkActions([
            ]);
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }
}
