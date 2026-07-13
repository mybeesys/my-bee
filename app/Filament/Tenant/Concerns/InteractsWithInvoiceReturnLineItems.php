<?php

namespace App\Filament\Tenant\Concerns;

use App\Models\InvoiceItem;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

trait InteractsWithInvoiceReturnLineItems
{
    protected static function returnLinesToolbar(): Forms\Components\Grid
    {
        return Forms\Components\Grid::make(1)
            ->extraAttributes(['class' => 'invoice-lines-toolbar fi-fo-grid'])
            ->schema([
                Forms\Components\Toggle::make('prices_includes_taxes')
                    ->default(true)
                    ->dehydrated(false)
                    ->label(__('fields.prices_includes_taxes'))
                    ->inline(true)
                    ->extraFieldWrapperAttributes(['class' => 'invoice-lines-toolbar__toggle'])
                    ->visible(fn (Get $get): bool => ($get('return_mode') ?? 'invoice') === 'invoice')
                    ->live()
                    ->afterStateUpdated(fn ($livewire) => static::refreshAllReturnDetails($livewire)),
            ]);
    }

    protected static function resolveReturnPricesIncludeTaxes(InvoiceItem $item, Get $get): bool
    {
        $returnMode = $get('data.return_mode') ?? $get('return_mode') ?? 'invoice';

        if ($returnMode === 'customer') {
            $item->loadMissing('invoice');

            return (bool) ($item->invoice?->prices_includes_taxes ?? true);
        }

        return (bool) ($get('data.prices_includes_taxes') ?? true);
    }

    public static function calculateReturnLineAmounts(InvoiceItem $item, float $returnQty, bool $pricesIncludesTaxes): array
    {
        $originalQty = (float) $item->getRawOriginal('qty');

        if ($originalQty <= 0 || $returnQty <= 0) {
            return [];
        }

        $netUnitPrice = (float) $item->price;
        $taxPerUnit = (float) $item->tax / $originalQty;
        $discountPerUnit = (float) $item->discount / $originalQty;

        $lineNet = $netUnitPrice * $returnQty;
        $lineTax = $taxPerUnit * $returnQty;
        $lineDiscount = $discountPerUnit * $returnQty;
        $total = $lineNet + $lineTax - $lineDiscount;

        $displayUnitPrice = $pricesIncludesTaxes
            ? $netUnitPrice + $taxPerUnit
            : $netUnitPrice;

        return [
            'unit_price' => $displayUnitPrice,
            'price' => $lineNet,
            'tax' => $lineTax,
            'discount' => $lineDiscount,
            'total' => $total,
        ];
    }

    protected static function formatReturnLineAmounts(array $amounts): array
    {
        $decimals = currency_decimals();
        $format = fn ($value) => number_format((float) $value, $decimals, '.', ',');

        return [
            'unit_price' => $format($amounts['unit_price']),
            'price' => $format($amounts['price']),
            'tax' => $format($amounts['tax']),
            'discount' => $format($amounts['discount']),
            'total' => $format($amounts['total']),
        ];
    }

    public static function applyReturnLineAmounts(
        Set $set,
        ?InvoiceItem $item,
        $returnQty,
        bool $pricesIncludesTaxes
    ): void {
        if (!$item || !$returnQty) {
            $set('unit_price', null);
            $set('price', null);
            $set('tax', null);
            $set('discount', null);
            $set('total', null);

            return;
        }

        $amounts = static::calculateReturnLineAmounts($item, (float) $returnQty, $pricesIncludesTaxes);

        if (empty($amounts)) {
            return;
        }

        foreach (static::formatReturnLineAmounts($amounts) as $key => $value) {
            $set($key, $value);
        }
    }

    public static function refreshAllReturnDetails(object $livewire): void
    {
        $details = $livewire->data['details'] ?? [];
        $returnMode = $livewire->data['return_mode'] ?? 'invoice';
        $defaultPricesIncludesTaxes = (bool) ($livewire->data['prices_includes_taxes'] ?? true);

        foreach ($details as $key => $detail) {
            if (empty($detail['invoice_item_id']) || empty($detail['qty'])) {
                continue;
            }

            $item = InvoiceItem::with('invoice')->find($detail['invoice_item_id']);

            if (!$item) {
                continue;
            }

            $pricesIncludesTaxes = $returnMode === 'customer'
                ? (bool) ($item->invoice?->prices_includes_taxes ?? true)
                : $defaultPricesIncludesTaxes;

            $amounts = static::calculateReturnLineAmounts($item, (float) $detail['qty'], $pricesIncludesTaxes);

            if (empty($amounts)) {
                continue;
            }

            $details[$key] = array_merge($detail, static::formatReturnLineAmounts($amounts));
        }

        $livewire->data['details'] = $details;
    }

    protected static function normalizeReturnDetailForSave(array $data): array
    {
        foreach (['discount', 'tax', 'price', 'total'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = str_replace(',', '', $data[$field]);
            }
        }

        return $data;
    }

    public static function sumReturnDetailsTotals(array $details): float
    {
        return collect($details)->sum(function ($detail) {
            $total = $detail['total'] ?? 0;

            if (is_string($total)) {
                $total = str_replace(',', '', $total);
            }

            return (float) $total;
        });
    }
}
