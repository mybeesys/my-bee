<?php

namespace App\Filament\Tenant\Widgets;


use App\Filament\Tenant\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrders extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('fields.latest_orders');
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('fields.table_empty_state'))
            ->heading(__('fields.latest_orders'))
            ->modelLabel(__('fields.order'))
            ->pluralModelLabel(__('fields.orders'))
            ->query(OrderResource::getEloquentQuery()->take(10))
            ->defaultPaginationPageOption(5)
            ->defaultSort('created_at', 'desc')
            ->columns(OrderResource::table(new Table($this))->getColumns())
            ->actions(OrderResource::table(new Table($this))->getActions());
//            ->columns([
//
//                Tables\Columns\TextColumn::make('no')
//                    ->label(__('fields.order_no'))
//                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('customer.name')
//                    ->label(__('fields.client'))
//                    ->searchable(),
//
//                Tables\Columns\TextColumn::make('payment_status')
//                    ->badge()
//                    ->label(__('fields.payment_status'))
//                    ->getStateUsing(fn(Order $record) => $record->invoice?->payment_status),
////
////                Tables\Columns\TextColumn::make('sub_total')
////                    ->toggleable()
////                    ->label(__('fields.sub_total'))
////                    ->tooltip(function ($record) {
////                        return format_amount($record->sub_total);
////                    })
////                    ->getStateUsing(function (Order $record) {
////                        return main_currency_iso_code() . " " . format_amount($record->sub_total);
////                    }),
//
//                Tables\Columns\TextColumn::make('total')
//                    ->toggleable()
//                    ->label(__('fields.total'))
//                    ->tooltip(function ($record) {
//                        return numbers_to_words($record->total);
//                    })
//                    ->getStateUsing(function (Order $record) {
//                        return main_currency_iso_code() . " " . format_amount($record->total);
//                    }),
//                Tables\Columns\TextColumn::make('created_at')
//                    ->label(__('fields.order_date'))
//                    ->dateTime('M j, Y')
//                    ->sortable(),
//
//            ])
//            ->actions([
//                Tables\Actions\Action::make('open')
//                    ->label(__('fields.view'))
//                    ->url(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record])),
//            ]);
    }
}
