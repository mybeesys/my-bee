<?php

namespace App\Filament\Exports;

use App\Models\AccountStatement;
use App\Models\CashDet;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CashDetExporter extends Exporter
{
    protected static ?string $model = CashDet::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('operation.no')
                ->label(__('fields.voucher_no')),

            ExportColumn::make('date')
                ->label(__('fields.date')),

            ExportColumn::make('account.name')
                ->label(__('fields.account')),

            ExportColumn::make('amount_in')
                ->label(__('fields.debit'))
                ->formatStateUsing(function (string $state, array $options): string {
                    return format_amount($state);
                }),

            ExportColumn::make('amount_out')
                ->label(__('fields.credit'))
                ->formatStateUsing(function (string $state, array $options): string {
                    return format_amount($state);
                }),

            ExportColumn::make('statement')
                ->label(__('fields.statement')),

            ExportColumn::make('balance_post_transaction')
                ->label(__('fields.balance'))
                ->formatStateUsing(function (string $state, array $options): string {
                    return format_amount($state);
                }),

            ExportColumn::make('created_at')
                ->label(__('fields.created_at')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your account statement export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
