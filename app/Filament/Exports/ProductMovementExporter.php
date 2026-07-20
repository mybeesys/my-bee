<?php

namespace App\Filament\Exports;

use App\Models\ProductMovementLine;
use App\Services\ProductMovementBalanceService;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProductMovementExporter extends Exporter
{
    protected static ?string $model = ProductMovementLine::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label(__('fields.name')),

            ExportColumn::make('movement_type')
                ->label(__('fields.type'))
                ->getStateUsing(fn (ProductMovementLine $record) => match ($record->movement_type) {
                    'purchases' => __('fields.products_movements_type_purchases'),
                    'sales' => __('fields.products_movements_type_sales'),
                    'sales_return' => __('fields.products_movements_type_sales_return'),
                    'purchase_return' => __('fields.products_movements_type_purchase_return'),
                    default => $record->movement_type,
                }),

            ExportColumn::make('entity_name')
                ->label(__('fields.entity')),

            ExportColumn::make('invoice_no')
                ->label(__('fields.invoice_no')),

            ExportColumn::make('qty')
                ->label(__('fields.qty')),

            ExportColumn::make('qty_after_movement')
                ->label(__('fields.qty_after_movement'))
                ->getStateUsing(fn (ProductMovementLine $record): float => app(ProductMovementBalanceService::class)->balanceAfterMovement($record)),

            ExportColumn::make('discount')
                ->label(__('fields.discount'))
                ->formatStateUsing(fn (string $state): string => format_amount($state)),

            ExportColumn::make('tax')
                ->label(__('fields.tax'))
                ->formatStateUsing(fn (string $state): string => format_amount($state)),

            ExportColumn::make('price')
                ->label(__('fields.price'))
                ->formatStateUsing(fn (string $state): string => format_amount($state)),

            ExportColumn::make('sub_total')
                ->label(__('fields.sub_total'))
                ->formatStateUsing(fn (string $state): string => format_amount($state)),

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
