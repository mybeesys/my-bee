<?php

namespace App\Filament\Exports;

use App\Models\InvoiceItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProductMovementExporter extends Exporter
{
    protected static ?string $model = InvoiceItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label(__('fields.name')),

            ExportColumn::make('type')
                ->label(__('fields.type'))
                ->getStateUsing(function (InvoiceItem $record) {
                    return $record->invoice->type == "purchases" ?
                        __('fields.products_movements_type_purchases')
                        : __('fields.products_movements_type_sales');
                }),

            ExportColumn::make('entity')
                ->label(__('fields.entity'))
                ->getStateUsing(function (InvoiceItem $record) {
                    if ($record->invoice->customer_id) {
                        return $record->invoice->customer?->name ?? '-';
                    }

                    return $record->invoice->supplier?->name ?? '-';
                }),

            ExportColumn::make('invoice.no')
                ->label(__('fields.invoice_no')),

            ExportColumn::make('qty')
                ->label(__('fields.qty')),

            ExportColumn::make('qty_after_movement')
                ->label(__('fields.qty_after_movement'))
                ->getStateUsing(fn (InvoiceItem $record): float => app(\App\Services\ProductMovementBalanceService::class)->balanceAfter($record)),

            ExportColumn::make('discount')
                ->label(__('fields.discount'))
                ->formatStateUsing(function (string $state, array $options): string {
                    return format_amount($state);
                }),

            ExportColumn::make('tax')
                ->label(__('fields.tax'))
                ->formatStateUsing(function (string $state, array $options): string {
                    return format_amount($state);
                }),

            ExportColumn::make('price')
                ->label(__('fields.price'))
                ->formatStateUsing(function (string $state, array $options): string {
                    return format_amount($state);
                }),

            ExportColumn::make('sub_total')
                ->label(__('fields.sub_total'))
                ->formatStateUsing(function (string $state, array $options): string {
                    return format_amount($state);
                }),

            ExportColumn::make('created_at')
                ->label(__('fields.created_at')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your product movement export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
