<?php

namespace App\Filament\Tenant\Concerns;

use App\Models\Acc4;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

trait InteractsWithInvoiceReturnLineItems
{
    protected static function returnFormValue(Get $get, string $key, ?object $livewire = null, mixed $default = null): mixed
    {
        $value = $get("data.{$key}", true);

        if (filled($value)) {
            return $value;
        }

        if ($livewire !== null && filled($livewire->data[$key] ?? null)) {
            return $livewire->data[$key];
        }

        return $default;
    }

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
                    ->live()
                    ->afterStateUpdated(fn ($livewire) => static::refreshAllReturnDetails($livewire)),
            ]);
    }

    protected static function returnPaymentSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make(__('fields.payments'))
            ->visible(fn (Get $get): bool => filled(static::returnFormValue($get, 'invoice_id'))
                || filled(static::returnFormValue($get, 'customer_id'))
                || filled(static::returnFormValue($get, 'supplier_id')))
            ->schema([
                Forms\Components\Select::make('payment_terms')
                    ->label(__('fields.payment_terms'))
                    ->options([
                        'cash' => __('fields.payment_terms_cash'),
                        'credit' => __('fields.payment_terms_credit'),
                    ])
                    ->default('cash')
                    ->native(false)
                    ->live(),

                Forms\Components\Group::make()
                    ->statePath('credit_payment_ui')
                    ->dehydrated(false)
                    ->visible(fn (Get $get): bool => (static::returnFormValue($get, 'payment_terms') ?? 'cash') === 'credit')
                    ->schema(static::returnCreditPaymentFieldsSchema())
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    /** @return array<int, Forms\Components\Component> */
    protected static function returnCreditPaymentFieldsSchema(): array
    {
        return [
            Forms\Components\Select::make('credit_payment_account')
                ->label(__('fields.account'))
                ->dehydrated(false)
                ->default(fn () => Acc4::defaultCollectionAccountCode())
                ->helperText(__('fields.payment_collection_account_hint'))
                ->options(fn () => Acc4::collectionAccountOptions())
                ->searchable(),

            Forms\Components\TextInput::make('credit_payment_amount')
                ->label(__('fields.paid_amount'))
                ->dehydrated(false)
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->live()
                ->maxValue(fn ($livewire) => static::returnCreditPaymentMaxAmount($livewire))
                ->currency(),

            Forms\Components\DatePicker::make('credit_payment_date')
                ->label(__('fields.payment_date'))
                ->dehydrated(false)
                ->default(now())
                ->maxDate(now())
                ->minDate(now()->subDays(90))
                ->displayFormat('d/m/Y'),

            Forms\Components\Textarea::make('credit_payment_statement')
                ->label(__('fields.additional_notes'))
                ->dehydrated(false)
                ->rows(3)
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('credit_payment_remaining')
                ->label(__('fields.unpaid_amount'))
                ->content(fn (Get $get, $livewire) => new HtmlString(
                    static::renderReturnCreditRemaining(
                        $livewire,
                        (float) (static::returnCreditPaymentUiValue($livewire, $get, 'credit_payment_amount') ?? 0),
                    )
                ))
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('credit_payment_status')
                ->label(__('fields.settlement_status'))
                ->content(fn (Get $get, $livewire) => new HtmlString(
                    '<span class="text-sm font-medium">' . e(static::returnCreditSettlementLabel(
                        $livewire,
                        (float) (static::returnCreditPaymentUiValue($livewire, $get, 'credit_payment_amount') ?? 0),
                    )) . '</span>'
                ))
                ->columnSpanFull(),
        ];
    }

    protected static function returnCreditPaymentUiValue($livewire, ?Get $get, string $key): mixed
    {
        if ($get) {
            $value = $get($key);

            if ($value !== null) {
                return $value;
            }
        }

        return $livewire->data['credit_payment_ui'][$key]
            ?? $livewire->data[$key]
            ?? null;
    }

    protected static function returnCreditPaymentMaxAmount($livewire): float
    {
        return max(0, static::returnDraftTotalFromLivewire($livewire));
    }

    protected static function returnDraftTotalFromLivewire($livewire): float
    {
        return static::sumReturnDetailsTotals($livewire->data['details'] ?? []);
    }

    protected static function returnCreditRemainingAmount($livewire, float $enteredAmount): float
    {
        return max(0, static::returnDraftTotalFromLivewire($livewire) - $enteredAmount);
    }

    protected static function renderReturnCreditRemaining($livewire, float $enteredAmount): string
    {
        $remaining = static::returnCreditRemainingAmount($livewire, $enteredAmount);
        $currency = main_currency_iso_code();

        return sprintf(
            '<span class="text-lg font-semibold text-danger-600">%s %s</span>',
            e(format_amount($remaining)),
            e($currency),
        );
    }

    protected static function returnCreditSettlementLabel($livewire, float $entered = 0): string
    {
        $total = static::returnDraftTotalFromLivewire($livewire);

        if ($entered <= 0) {
            return __('fields.settlement_status_due');
        }

        if ($entered >= $total && $total > 0) {
            return __('fields.settlement_status_paid');
        }

        return __('fields.settlement_status_partial');
    }

    public static function settleSalesReturnPayment(object $record, array $formData, float $returnTotal): void
    {
        $paymentTerms = (string) ($formData['payment_terms'] ?? 'cash');
        $invoiceId = $record->invoice_id;
        $returnId = $record->id;

        if ($paymentTerms === 'cash') {
            static::postSalesReturnAccounting($record, $returnTotal, 'cash', '120100001', $invoiceId, $returnId);

            return;
        }

        $refundAmount = static::normalizeReturnCreditPaymentAmount(
            static::returnCreditPaymentAmountFromFormData($formData)
        );
        $accountAmount = max(0, $returnTotal - $refundAmount);

        if ($accountAmount > 0) {
            static::postSalesReturnAccounting($record, $accountAmount, 'credit', null, $invoiceId, $returnId);
        }
    }

    public static function settlePurchaseReturnPayment(object $record, array $formData, float $returnTotal): void
    {
        $paymentTerms = (string) ($formData['payment_terms'] ?? 'cash');
        $invoiceId = $record->invoice_id;
        $returnId = $record->id;

        if ($paymentTerms === 'cash') {
            static::postPurchaseReturnAccounting($record, $returnTotal, 'cash', '120100001', $invoiceId, $returnId);

            return;
        }

        $refundAmount = static::normalizeReturnCreditPaymentAmount(
            static::returnCreditPaymentAmountFromFormData($formData)
        );
        $accountAmount = max(0, $returnTotal - $refundAmount);

        if ($accountAmount > 0) {
            static::postPurchaseReturnAccounting($record, $accountAmount, 'credit', null, $invoiceId, $returnId);
        }
    }

    protected static function returnCreditPaymentAmountFromFormData(array $formData): mixed
    {
        if (isset($formData['credit_payment_ui']) && is_array($formData['credit_payment_ui'])) {
            return $formData['credit_payment_ui']['credit_payment_amount'] ?? 0;
        }

        return $formData['credit_payment_amount'] ?? 0;
    }

    protected static function normalizeReturnCreditPaymentAmount(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return 0;
        }

        return (float) preg_replace('/[^\d.]/', '', $value);
    }

    protected static function resolveReturnPricesIncludeTaxes(InvoiceItem $item, Get $get): bool
    {
        return (bool) (static::returnFormValue($get, 'prices_includes_taxes') ?? true);
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
        $defaultPricesIncludesTaxes = (bool) ($livewire->data['prices_includes_taxes'] ?? true);
        $returnMode = $livewire->data['return_mode'] ?? 'invoice';

        foreach ($details as $key => $detail) {
            if (empty($detail['qty'])) {
                continue;
            }

            if (in_array($returnMode, ['customer', 'supplier'], true) && ! empty($detail['product_line_key'])) {
                $amounts = static::calculateProductReturnLineAmounts(
                    (string) $detail['product_line_key'],
                    (float) $detail['qty'],
                    $defaultPricesIncludesTaxes,
                    $returnMode === 'supplier' ? 'purchases' : 'sales',
                    $returnMode === 'customer' ? (int) ($livewire->data['customer_id'] ?? 0) : null,
                    $returnMode === 'supplier' ? (int) ($livewire->data['supplier_id'] ?? 0) : null,
                );
            } elseif (! empty($detail['invoice_item_id'])) {
                $item = InvoiceItem::with('invoice')->find($detail['invoice_item_id']);

                if (! $item) {
                    continue;
                }

                $amounts = static::calculateReturnLineAmounts($item, (float) $detail['qty'], $defaultPricesIncludesTaxes);
            } else {
                continue;
            }

            if (empty($amounts)) {
                continue;
            }

            $details[$key] = array_merge($detail, static::formatReturnLineAmounts($amounts));
        }

        $livewire->data['details'] = $details;
    }

    public static function buildProductLineKey(InvoiceItem $item): string
    {
        return $item->product_id . ':' . ($item->product_variant_id ?? 0);
    }

    /** @return array{0: int, 1: ?int} */
    public static function parseProductLineKey(string $productKey): array
    {
        [$productId, $variantId] = array_pad(explode(':', $productKey, 2), 2, 0);

        return [(int) $productId, ((int) $variantId) ?: null];
    }

    /** @return Collection<int, InvoiceItem> */
    public static function returnableInvoiceItemsForProductKey(
        string $productKey,
        string $invoiceType = 'sales',
        ?int $customerId = null,
        ?int $supplierId = null,
    ): Collection {
        [$productId, $variantId] = static::parseProductLineKey($productKey);

        $query = static::returnableInvoiceItemsQuery($invoiceType)
            ->where('product_id', $productId);

        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        if ($customerId) {
            $query->whereHas('invoice', fn (Builder $q) => $q->where('customer_id', $customerId));
        }

        if ($supplierId) {
            $query->whereHas('invoice', fn (Builder $q) => $q->where('supplier_id', $supplierId));
        }

        return $query->orderBy('id')->get();
    }

    public static function getReturnableProductQty(
        string $productKey,
        string $invoiceType = 'sales',
        ?int $customerId = null,
        ?int $supplierId = null,
    ): float {
        return (float) static::returnableInvoiceItemsForProductKey(
            $productKey,
            $invoiceType,
            $customerId,
            $supplierId,
        )->sum(fn (InvoiceItem $item) => (float) $item->qty);
    }

    public static function findTemplateInvoiceItemForProductKey(
        string $productKey,
        string $invoiceType = 'sales',
        ?int $customerId = null,
        ?int $supplierId = null,
    ): ?InvoiceItem {
        return static::returnableInvoiceItemsForProductKey(
            $productKey,
            $invoiceType,
            $customerId,
            $supplierId,
        )->first();
    }

    /** @return array<string, string> */
    protected static function returnableProductOptionsFromItems(Collection $items): array
    {
        $options = [];

        foreach ($items->groupBy(fn (InvoiceItem $item) => static::buildProductLineKey($item)) as $key => $group) {
            $available = $group->sum(fn (InvoiceItem $item) => (float) $item->qty);

            if ($available <= 0) {
                continue;
            }

            $sample = $group->first();
            $options[$key] = $sample->name ?: $sample->product?->name ?? '—';
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function returnableProductOptionsForCustomer(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $items = static::returnableInvoiceItemsQuery()
            ->whereHas('invoice', fn (Builder $query) => $query->where('customer_id', $customerId))
            ->get();

        return static::returnableProductOptionsFromItems($items);
    }

    /** @return array<string, string> */
    public static function returnableProductOptionsForSupplier(int $supplierId): array
    {
        if ($supplierId <= 0) {
            return [];
        }

        $items = static::returnableInvoiceItemsQuery('purchases')
            ->whereHas('invoice', fn (Builder $query) => $query->where('supplier_id', $supplierId))
            ->get();

        return static::returnableProductOptionsFromItems($items);
    }

    public static function calculateProductReturnLineAmounts(
        string $productKey,
        float $returnQty,
        bool $pricesIncludesTaxes,
        string $invoiceType = 'sales',
        ?int $customerId = null,
        ?int $supplierId = null,
    ): array {
        if ($returnQty <= 0) {
            return [];
        }

        $items = static::returnableInvoiceItemsForProductKey(
            $productKey,
            $invoiceType,
            $customerId,
            $supplierId,
        );

        $remaining = $returnQty;
        $totals = [
            'price' => 0.0,
            'tax' => 0.0,
            'discount' => 0.0,
            'total' => 0.0,
        ];

        foreach ($items as $item) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (float) $item->qty);

            if ($take <= 0) {
                continue;
            }

            $chunk = static::calculateReturnLineAmounts($item, $take, $pricesIncludesTaxes);

            if (empty($chunk)) {
                continue;
            }

            foreach ($totals as $field => $value) {
                $totals[$field] += (float) ($chunk[$field] ?? 0);
            }

            $remaining -= $take;
        }

        if ($totals['total'] <= 0) {
            return [];
        }

        $totals['unit_price'] = $pricesIncludesTaxes
            ? ($totals['price'] + $totals['tax']) / $returnQty
            : $totals['price'] / $returnQty;

        return $totals;
    }

    public static function applyProductReturnLineAmounts(
        Set $set,
        string $productKey,
        $returnQty,
        bool $pricesIncludesTaxes,
        string $invoiceType = 'sales',
        ?int $customerId = null,
        ?int $supplierId = null,
    ): void {
        if (! $productKey || ! $returnQty) {
            $set('unit_price', null);
            $set('price', null);
            $set('tax', null);
            $set('discount', null);
            $set('total', null);

            return;
        }

        $amounts = static::calculateProductReturnLineAmounts(
            $productKey,
            (float) $returnQty,
            $pricesIncludesTaxes,
            $invoiceType,
            $customerId,
            $supplierId,
        );

        if (empty($amounts)) {
            return;
        }

        foreach (static::formatReturnLineAmounts($amounts) as $key => $value) {
            $set($key, $value);
        }
    }

    protected static function resolvePricingInvoiceItem(Get $get, ?object $livewire = null): ?InvoiceItem
    {
        $returnMode = static::returnFormValue($get, 'return_mode', $livewire, 'invoice');

        if (in_array($returnMode, ['customer', 'supplier'], true)) {
            $productKey = $get('product_line_key');

            if (! $productKey) {
                return null;
            }

            return static::findTemplateInvoiceItemForProductKey(
                (string) $productKey,
                $returnMode === 'supplier' ? 'purchases' : 'sales',
                $returnMode === 'customer' ? (int) static::returnFormValue($get, 'customer_id', $livewire) : null,
                $returnMode === 'supplier' ? (int) static::returnFormValue($get, 'supplier_id', $livewire) : null,
            );
        }

        return InvoiceItem::with('invoice')->find($get('invoice_item_id'));
    }

    /** @return array<int, array<string, mixed>> */
    public static function expandReturnDetailsForStorage(
        array $details,
        string $returnMode,
        array $context,
        string $invoiceType = 'sales',
    ): array {
        $pricesIncludesTaxes = (bool) ($context['prices_includes_taxes'] ?? true);
        $customerId = isset($context['customer_id']) ? (int) $context['customer_id'] : null;
        $supplierId = isset($context['supplier_id']) ? (int) $context['supplier_id'] : null;
        $expanded = [];

        foreach ($details as $detail) {
            if ($returnMode === 'invoice') {
                $expanded[] = static::normalizeReturnDetailForSave($detail);

                continue;
            }

            $productKey = $detail['product_line_key'] ?? null;

            if (! $productKey) {
                continue;
            }

            $requestedQty = (float) ($detail['qty'] ?? 0);
            $items = static::returnableInvoiceItemsForProductKey(
                (string) $productKey,
                $invoiceType,
                $customerId ?: null,
                $supplierId ?: null,
            );

            $remaining = $requestedQty;

            foreach ($items as $item) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (float) $item->qty);

                if ($take <= 0) {
                    continue;
                }

                $amounts = static::calculateReturnLineAmounts($item, $take, $pricesIncludesTaxes);

                $expanded[] = static::normalizeReturnDetailForSave([
                    'invoice_item_id' => $item->id,
                    'qty' => $take,
                    'price' => $amounts['price'] ?? 0,
                    'tax' => $amounts['tax'] ?? 0,
                    'discount' => $amounts['discount'] ?? 0,
                    'total' => $amounts['total'] ?? 0,
                    'user_id' => $detail['user_id'] ?? null,
                    'tenant_id' => $detail['tenant_id'] ?? null,
                ]);

                $remaining -= $take;
            }
        }

        return $expanded;
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

    protected static function returnableInvoiceItemsQuery(string $invoiceType = 'sales'): Builder
    {
        return InvoiceItem::query()
            ->with(['product', 'productVariant', 'invoice'])
            ->where('cancelled', false)
            ->where('qty', '>', 0)
            ->whereHas('invoice', fn (Builder $query) => $query
                ->where('type', $invoiceType)
                ->where('temp', false)
                ->where('status', 'confirmed'));
    }

    /** @return array<int, string> */
    public static function returnableInvoiceOptions(string $invoiceType, bool $forCreate = true): array
    {
        $query = Invoice::query()
            ->with($invoiceType === 'sales' ? ['customer'] : ['supplier'])
            ->where('type', $invoiceType)
            ->where('temp', false)
            ->where('status', 'confirmed')
            ->whereHas('items', fn (Builder $q) => $q
                ->where('cancelled', false)
                ->where('qty', '>', 0));

        if ($forCreate) {
            // Keep invoices that still have at least one returnable line.
        }

        $options = [];

        foreach ($query->orderByDesc('id')->get() as $invoice) {
            $partyName = $invoiceType === 'sales'
                ? ($invoice->customer?->name ?? '-')
                : ($invoice->supplier?->name ?? '-');

            $options[$invoice->id] = $partyName . ' - ' . $invoice->no;
        }

        return $options;
    }

    /** @return array<int, string> */
    public static function returnableInvoiceItemOptionsForInvoice(int $invoiceId, bool $warnWhenEmpty = false): array
    {
        if ($invoiceId <= 0) {
            return [];
        }

        $options = [];

        foreach (static::returnableInvoiceItemsQuery()->where('invoice_id', $invoiceId)->get() as $item) {
            if ($item->qty <= 0) {
                if ($warnWhenEmpty) {
                    fns()->sendWarning(__('fields.sales_return_item_already_returned'));
                }

                continue;
            }

            $options[$item->id] = $item->name ?: $item->product?->name ?? '—';
        }

        return $options;
    }

    public static function syncExpandedReturnDetails(object $record, array $formData, string $returnMode, string $invoiceType = 'sales'): float
    {
        if (! in_array($returnMode, ['customer', 'supplier'], true)) {
            return static::sumReturnDetailsTotals($formData['details'] ?? []);
        }

        $record->details()->delete();

        $expanded = static::expandReturnDetailsForStorage(
            $formData['details'] ?? [],
            $returnMode,
            [
                'customer_id' => $formData['customer_id'] ?? null,
                'supplier_id' => $formData['supplier_id'] ?? null,
                'prices_includes_taxes' => (bool) ($formData['prices_includes_taxes'] ?? true),
            ],
            $invoiceType,
        );

        $userId = $formData['user_id'] ?? filament()->auth()->id() ?? auth()->id();
        $tenantId = filament()->getTenant()?->id;

        foreach ($expanded as $row) {
            $record->details()->create([
                ...$row,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]);
        }

        $record->load('details');

        return (float) $record->details->sum('total');
    }

    public static function postSalesReturnAccounting(
        object $record,
        float $amount,
        string $paymentTerms,
        ?string $refundAcc4Code,
        ?int $invoiceId,
        int $returnId,
    ): void {
        $customer = $record->resolveCustomer();

        if (! $customer?->acc4?->code) {
            fns()->sendWarning(__('fields.invoice_payment_missing_customer_account'));
            return;
        }

        $op = make_general_voucher_op();
        $accService = new \App\Services\AccountingService();
        $accService->setUp(
            $op->id,
            now(),
            main_currency_iso_code(),
            generate_double_entry_transaction_id(),
            $amount,
            null,
            'Return paid amount to customer - إرجاع المبلغ المدفوع للعميل',
            'Return paid amount to customer - إرجاع المبلغ المدفوع للعميل',
            $invoiceId,
            meta: ['type' => 'sales_returns', 'id' => $returnId],
        );

        if ($paymentTerms === 'cash') {
            $accService->make((string) $refundAcc4Code, (string) $customer->acc4->code);
        } else {
            $accService->make((string) $customer->acc4->code, '121900001');
        }

        $accService->finish();
    }

    public static function postPurchaseReturnAccounting(
        object $record,
        float $amount,
        string $paymentTerms,
        ?string $refundAcc4Code,
        ?int $invoiceId,
        int $returnId,
    ): void {
        $supplier = $record->resolveSupplier();

        if (! $supplier?->acc4?->code) {
            fns()->sendWarning(__('fields.invoice_payment_missing_supplier_account'));
            return;
        }

        $op = make_general_voucher_op();
        $accService = new \App\Services\AccountingService();
        $accService->setUp(
            $op->id,
            now(),
            main_currency_iso_code(),
            generate_double_entry_transaction_id(),
            $amount,
            null,
            'Return paid amount to treasury - إرجاع المبلغ المدفوع للصندوق',
            'Return paid amount to treasury - إرجاع المبلغ المدفوع للصندوق',
            $invoiceId,
            meta: ['type' => 'purchases_returns', 'id' => $returnId],
        );

        if ($paymentTerms === 'cash') {
            $accService->make((string) $supplier->acc4->code, (string) $refundAcc4Code);
        } else {
            $accService->make('121900001', (string) $supplier->acc4->code);
        }

        $accService->finish();
    }
}
