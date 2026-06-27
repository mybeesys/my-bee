<?php

namespace App\Filament\Tenant\Concerns;

use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Services\PricingService;
use App\Services\StockService;
use Awcodes\TableRepeater\Header;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Enums\Alignment;

trait InlineProductLineItems
{
    /**
     * TableRepeater aligns headers to visible schema fields by position (not by name).
     * Keep invoiceLineTableSchema() field order identical to this list.
     *
     * @return array<int, Header>
     */
    protected static function supplyOrderLineTableHeaders(): array
    {
        $align = fn () => app()->getLocale() == 'ar' ? Alignment::Left : Alignment::Right;

        return [
            Header::make('product_id')
                ->width('200px')
                ->align($align)
                ->markAsRequired()
                ->label(__('fields.product')),

            Header::make('qty')
                ->width('80px')
                ->align($align)
                ->markAsRequired()
                ->label(__('fields.qty')),
        ];
    }

    protected static function purchaseInvoiceLineTableHeaders(): array
    {
        $align = fn () => app()->getLocale() == 'ar' ? Alignment::Left : Alignment::Right;

        return [
            Header::make('product_id')
                ->width('200px')
                ->align($align)
                ->markAsRequired()
                ->label(__('fields.product')),

            Header::make('qty')
                ->width('80px')
                ->align($align)
                ->markAsRequired()
                ->label(__('fields.qty')),

            Header::make('price')
                ->width('120px')
                ->align($align)
                ->markAsRequired()
                ->label(__('fields.purchase_price')),

            Header::make('discount')
                ->width('100px')
                ->align($align)
                ->label(__('fields.discount')),

            Header::make('tax_profile_id')
                ->width('200px')
                ->align($align)
                ->label(__('fields.tax_profile')),

            Header::make('tax')
                ->width('120px')
                ->align($align)
                ->label(__('fields.tax')),

            Header::make('sub_total')
                ->width('120px')
                ->align($align)
                ->label(__('fields.sub_total')),
        ];
    }

    protected static function invoiceLineTableHeaders(): array
    {
        $align = fn () => app()->getLocale() == 'ar' ? Alignment::Left : Alignment::Right;

        return [
            Header::make('product_id')
                ->width('200px')
                ->align($align)
                ->markAsRequired()
                ->label(__('fields.product')),

            Header::make('product_extras_ids')
                ->width('150px')
                ->align($align)
                ->label(__('fields.product_extras')),

            Header::make('qty')
                ->width('80px')
                ->align($align)
                ->markAsRequired()
                ->label(__('fields.qty')),

            Header::make('price')
                ->width('120px')
                ->align($align)
                ->markAsRequired()
                ->label(__('fields.price')),

            Header::make('discount')
                ->width('100px')
                ->align($align)
                ->label(__('fields.discount')),

            Header::make('tax_profile_id')
                ->width('200px')
                ->align($align)
                ->label(__('fields.tax_profile')),

            Header::make('tax')
                ->width('120px')
                ->align($align)
                ->label(__('fields.tax')),

            Header::make('sub_total')
                ->width('120px')
                ->align($align)
                ->label(__('fields.sub_total')),
        ];
    }

    protected static function inlineProductSelect(
        string $nameField,
        callable $recalculate,
        bool $prefillUnitPrice = true,
        bool $limitQtyByStock = true,
    ): Select {
        return Select::make('product_id')
            ->label(__('fields.product'))
            ->searchable()
            ->searchPrompt(__('fields.search_product_by_name_or_sku'))
            ->getSearchResultsUsing(function (string $search): array {
                $products = Product::query()
                    ->with('variants')
                    ->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%")
                            ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('name', 'like', "%{$search}%"));
                    })
                    ->orderBy('name')
                    ->limit(50)
                    ->get();

                return static::mapProductsAndVariantsForInlineSelect($products);
            })
            ->getOptionLabelUsing(fn ($value): ?string => static::resolveInlineProductLineLabel($value))
            ->options(function (): array {
                $products = Product::query()->with('variants')->orderBy('name')->limit(100)->get();

                return static::mapProductsAndVariantsForInlineSelect($products);
            })
            ->formatStateUsing(function ($state, Get $get): ?string {
                if ($variantId = $get('product_variant_id')) {
                    return static::inlineVariantLineKey((int) $variantId);
                }

                if (filled($state) && is_string($state) && (str_starts_with($state, 'p:') || str_starts_with($state, 'v:'))) {
                    return $state;
                }

                return filled($state) ? static::inlineProductLineKey((int) $state) : null;
            })
            ->live()
            ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire) use ($nameField, $recalculate, $prefillUnitPrice, $limitQtyByStock) {
                static::handleInlineProductLineSelection($state, $set, $get, $livewire, $nameField, $recalculate, $prefillUnitPrice, $limitQtyByStock);
            });
    }

    /**
     * @param  iterable<int, Product>  $products
     * @return array<string, string>
     */
    protected static function mapProductsAndVariantsForInlineSelect(iterable $products): array
    {
        $options = [];

        foreach ($products as $product) {
            if ($product->type === Product::$TYPE_VARIANTS) {
                foreach ($product->variants as $variant) {
                    $options[static::inlineVariantLineKey($variant->id)] = trim(
                        $product->name . ' — ' . $variant->name . ($product->sku ? " ({$product->sku})" : '')
                    );
                }

                continue;
            }

            $options[static::inlineProductLineKey($product->id)] = trim(
                $product->name . ($product->sku ? " ({$product->sku})" : '')
            );
        }

        return $options;
    }

    protected static function inlineProductLineKey(int $productId): string
    {
        return 'p:' . $productId;
    }

    protected static function inlineVariantLineKey(int $variantId): string
    {
        return 'v:' . $variantId;
    }

    protected static function resolveInlineProductLineLabel(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        if (is_string($value) && str_starts_with($value, 'v:')) {
            $variant = ProductVariant::with('product')->find((int) substr($value, 2));

            if (! $variant) {
                return null;
            }

            return trim(
                $variant->product->name . ' — ' . $variant->name . ($variant->product->sku ? " ({$variant->product->sku})" : '')
            );
        }

        $productId = is_string($value) && str_starts_with($value, 'p:')
            ? (int) substr($value, 2)
            : (int) $value;

        $product = Product::find($productId);

        if (! $product) {
            return null;
        }

        return trim($product->name . ($product->sku ? " ({$product->sku})" : ''));
    }

    protected static function handleInlineProductLineSelection(
        mixed $state,
        Set $set,
        Get $get,
        $livewire,
        string $nameField,
        callable $recalculate,
        bool $prefillUnitPrice = true,
        bool $limitQtyByStock = true,
    ): void {
        if (is_string($state) && str_starts_with($state, 'v:')) {
            static::handleInlineVariantChange((int) substr($state, 2), $set, $get, $livewire, $nameField, $recalculate, $prefillUnitPrice, $limitQtyByStock);

            return;
        }

        $productId = is_string($state) && str_starts_with($state, 'p:')
            ? (int) substr($state, 2)
            : (is_numeric($state) ? (int) $state : null);

        static::handleInlineProductChange($productId, $set, $get, $livewire, $nameField, $recalculate, $prefillUnitPrice, $limitQtyByStock);
    }

    protected static function handleInlineProductChange(
        $state,
        Set $set,
        Get $get,
        $livewire,
        string $nameField,
        callable $recalculate,
        bool $prefillUnitPrice = true,
        bool $limitQtyByStock = true,
    ): void
    {
        if (! $state) {
            static::clearInlineProductRow($set, $nameField);

            $recalculate($livewire);

            return;
        }

        $product = Product::with(['variants', 'extras'])->find($state);

        if (! $product) {
            fns()->sendDanger('Product not found!');
            static::clearInlineProductRow($set, $nameField);

            $recalculate($livewire);

            return;
        }

        $tenantId = $get('tenant_id') ?? filament()->getTenant()->id;
        $set('tenant_id', $tenantId);
        $set('product_extras_ids', []);
        $set('available_product_extras_ids', $product->extras->pluck('id')->toArray());
        $set('extras', '');
        $set('extras_total', 0);

        if ($product->type === Product::$TYPE_VARIANTS) {
            fns()->sendWarning(__('fields.choose_product_variant_from_list'));

            static::clearInlineProductRow($set, $nameField);

            $recalculate($livewire);

            return;
        }

        static::fillInlineBasicProduct($set, $get, $product, $nameField, $tenantId, $prefillUnitPrice, $limitQtyByStock);
        $set('product_variant_id', null);

        $recalculate($livewire);
    }

    protected static function handleInlineVariantChange(
        $state,
        Set $set,
        Get $get,
        $livewire,
        string $nameField,
        callable $recalculate,
        bool $prefillUnitPrice = true,
        bool $limitQtyByStock = true,
    ): void
    {
        if (! $state) {
            $set('item_id', null);
            $set('item_type', null);
            $set($nameField, '');
            $set('unit_price', null);
            $set('price', null);
            $set('sub_total', null);

            $recalculate($livewire);

            return;
        }

        $variant = ProductVariant::with(['product.extras', 'product.taxProfile'])->find($state);

        if (! $variant) {
            fns()->sendDanger('Option not found');

            $recalculate($livewire);

            return;
        }

        static::fillInlineVariantProduct(
            $set,
            $get,
            $variant,
            $nameField,
            $get('tenant_id') ?? filament()->getTenant()->id,
            $prefillUnitPrice,
            $limitQtyByStock,
        );

        $recalculate($livewire);
    }

    protected static function clearInlineProductRow(Set $set, string $nameField): void
    {
        $set('type', null);
        $set($nameField, '');
        $set('item_id', null);
        $set('item_type', null);
        $set('product_variant_id', null);
        $set('unit_price', null);
        $set('price', null);
        $set('sub_total', null);
        $set('tax', 0);
        $set('product_extras_ids', []);
        $set('available_product_extras_ids', []);
        $set('extras', '');
        $set('extras_total', 0);
    }

    protected static function fillInlineBasicProduct(
        Set $set,
        Get $get,
        Product $product,
        string $nameField,
        int | string $tenantId,
        bool $prefillUnitPrice = true,
        bool $limitQtyByStock = true,
    ): void {
        $qty = is_numeric($get('qty')) && $get('qty') > 0 ? $get('qty') : 1;

        $set('type', $product->type);
        $set($nameField, $product->name);
        $set('product_id', $product->id);
        $set('item_id', $product->id);
        $set('item_type', Product::class);

        if ($limitQtyByStock) {
            $set('max_qty', StockService::instance()->getAvailableStock($product));
        }

        $set('qty', $qty);
        $set('tenant_id', $tenantId);

        if ($prefillUnitPrice) {
            $price = PricingService::instance()->getRetailPrice($product);
            $formattedPrice = number_format($price, currency_decimals(), '.', '');
            $lineTotal = number_format($qty * $price, currency_decimals(), '.', '');
            $set('unit_price', $formattedPrice);
            $set('price', $lineTotal);
            $set('sub_total', $lineTotal);
        }

        if ($nameField === 'display_name') {
            $set('tax_profile_id', $product->tax_profile_id);
        } else {
            $set('item_id', $product->id);
            $set('item_type', Product::class);
            $set('tax_profile_id', null);
            $set('tax_profile_data', null);
            $set('product_variant_id', null);
        }
    }

    protected static function fillInlineVariantProduct(
        Set $set,
        Get $get,
        ProductVariant $variant,
        string $nameField,
        int | string $tenantId,
        bool $prefillUnitPrice = true,
        bool $limitQtyByStock = true,
    ): void {
        $product = $variant->product;
        $qty = is_numeric($get('qty')) && $get('qty') > 0 ? $get('qty') : 1;

        $set('type', 'variants');
        $set($nameField, $variant->name);
        $set('item_id', $variant->id);
        $set('item_type', ProductVariant::class);
        $set('product_id', $product->id);
        $set('product_variant_id', $variant->id);

        if ($limitQtyByStock) {
            $set('max_qty', StockService::instance()->getAvailableStock($variant));
        }

        $set('qty', $qty);
        $set('available_product_extras_ids', $product->extras->pluck('id')->toArray());
        $set('tenant_id', $tenantId);

        if ($prefillUnitPrice) {
            $price = PricingService::instance()->getRetailPrice($variant);
            $formattedPrice = number_format($price, currency_decimals(), '.', '');
            $lineTotal = number_format($qty * $price, currency_decimals(), '.', '');
            $set('unit_price', $formattedPrice);
            $set('price', $lineTotal);
            $set('sub_total', $lineTotal);
        }

        if ($nameField === 'display_name') {
            $set('tax_profile_id', $product->tax_profile_id);
        } else {
            $set('item_id', $variant->id);
            $set('item_type', ProductVariant::class);
            $set('tax_profile_id', null);
            $set('tax_profile_data', null);
        }
    }

    public static function hydrateInlineProductRow(array $item): array
    {
        if (($item['item_type'] ?? null) === ProductVariant::class && ! empty($item['item_id'])) {
            $variant = ProductVariant::find($item['item_id']);
            $item['product_variant_id'] = $variant?->id;
            $item['product_id'] = $variant?->product_id;
            $item['type'] = Product::$TYPE_VARIANTS;
        } elseif (($item['item_type'] ?? null) === Product::class && ! empty($item['item_id'])) {
            $item['product_id'] = $item['item_id'];
            $item['product_variant_id'] = null;
            $item['type'] = Product::$TYPE_BASIC;
        }

        if (! empty($item['product_variant_id'])) {
            $item['type'] = Product::$TYPE_VARIANTS;
            $item['product_id'] = static::inlineVariantLineKey((int) $item['product_variant_id']);
        } elseif (! empty($item['product_id'])) {
            $item['type'] = $item['type'] ?? Product::$TYPE_BASIC;

            if (is_numeric($item['product_id'])) {
                $item['product_id'] = static::inlineProductLineKey((int) $item['product_id']);
            }
        }

        return $item;
    }

    /**
     * @param  array<int|string, mixed>  $state
     * @return array<int, array<string, mixed>>
     */
    public static function inlineProductLinesFromState(array $state): array
    {
        return collect($state)
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();
    }

    /**
     * Table/repeater reorder sends order keys only (e.g. [2, 0, 1]) — map them to cached rows.
     *
     * @param  array<int|string, mixed>  $order
     * @param  array<int|string, mixed>  $previous
     * @return array<int, array<string, mixed>>
     */
    protected static function reorderInlineProductLines(array $order, array $previous): array
    {
        $previousRows = static::inlineProductLinesFromState($previous);

        if ($order === [] || $previousRows === []) {
            return $previousRows;
        }

        $rows = [];

        foreach ($order as $key) {
            if (is_array($key)) {
                $rows[] = $key;

                continue;
            }

            if (is_array($previous[$key] ?? null)) {
                $rows[] = $previous[$key];

                continue;
            }

            if (is_numeric($key) && is_array($previous[(int) $key] ?? null)) {
                $rows[] = $previous[(int) $key];
            }
        }

        return count($rows) === count($order) ? $rows : $previousRows;
    }

    protected static function isInlineProductLinesOrderPayload(array $state): bool
    {
        return array_is_list($state)
            && ! collect($state)->contains(fn ($value) => is_array($value));
    }

    protected static function isEmptyInlineProductRow(mixed $item): bool
    {
        if (! is_array($item)) {
            return true;
        }

        return empty($item['item_id']);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function emptyInvoiceLineRow(): array
    {
        return [
            'tenant_id' => filament()->getTenant()->id,
            'qty' => 1,
            'discount' => number_format(0, currency_decimals(), '.', ''),
            'product_extras_ids' => [],
            'available_product_extras_ids' => [],
            'extras_total' => 0,
            'tax' => number_format(0, currency_decimals(), '.', ''),
        ];
    }

    public static function ensureDefaultInvoiceLineOnCreate(object $livewire, string $linesKey): void
    {
        if (! empty(static::inlineProductLinesFromState($livewire->data[$linesKey] ?? []))) {
            return;
        }

        $livewire->data[$linesKey] = [
            \Illuminate\Support\Str::uuid()->toString() => static::emptyInvoiceLineRow(),
        ];

        $livewire->cachedInvoiceLineItems = $livewire->data[$linesKey];
    }

    public static function normalizeInlineProductRowForSave(array $item): array
    {
        if (($item['item_type'] ?? null) === ProductVariant::class && ! empty($item['item_id'])) {
            $item['product_variant_id'] = (int) $item['item_id'];
            $item['product_id'] = ProductVariant::find($item['product_variant_id'])?->product_id;
        } elseif (($item['item_type'] ?? null) === Product::class && ! empty($item['item_id'])) {
            $item['product_id'] = (int) $item['item_id'];
            $item['product_variant_id'] = null;
        } else {
            $variantId = static::resolveInlineVariantIdValue($item['product_variant_id'] ?? null)
                ?? static::resolveInlineVariantIdValue($item['product_id'] ?? null);

            $productId = static::resolveInlineProductIdValue($item['product_id'] ?? null);

            if ($productId) {
                $item['product_id'] = $productId;
            }

            if ($variantId) {
                $item['product_variant_id'] = $variantId;

                if (empty($item['product_id'])) {
                    $item['product_id'] = ProductVariant::find($variantId)?->product_id;
                }

                if (empty($item['item_id'])) {
                    $item['item_id'] = $variantId;
                    $item['item_type'] = ProductVariant::class;
                }
            } elseif (! empty($item['product_id']) && empty($item['item_id'])) {
                $item['item_id'] = (int) $item['product_id'];
                $item['item_type'] = Product::class;
            }
        }

        return $item;
    }

    protected static function resolveInlineProductIdValue(mixed $value): ?int
    {
        if (! filled($value)) {
            return null;
        }

        if (is_string($value) && str_starts_with($value, 'p:')) {
            return (int) substr($value, 2);
        }

        if (is_string($value) && str_starts_with($value, 'v:')) {
            return ProductVariant::find((int) substr($value, 2))?->product_id;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected static function resolveInlineVariantIdValue(mixed $value): ?int
    {
        if (! filled($value)) {
            return null;
        }

        if (is_string($value) && str_starts_with($value, 'v:')) {
            return (int) substr($value, 2);
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
