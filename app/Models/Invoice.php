<?php

namespace App\Models;

use App\Filament\Tenant\Resources\PaymentVoucherResource;
use App\Filament\Tenant\Resources\ReceiptVoucherResource;
use App\Services\AccountingService;
use App\Services\InvoicePaymentTermsService;
use App\Services\MathService;
use App\Services\PricingService;
use App\Services\StockService;
use App\Traits\HasPrefixedId;
use Dompdf\Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Invoice extends BaseModel
{
    use HasFactory, HasPrefixedId;

    protected $guarded = [];

    protected $casts = [
        'files' => 'array',
        'inventory_taken_from_warehouses' => 'array',
        'date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static string $TYPE_PURCHASES = 'purchases';
    public static string $TYPE_SALES = 'sales';


    public static function boot()
    {
        parent::boot();

        //while creating/inserting set default status
        static::created(function (Invoice $item) {
//            if ($item->type === "purchases") {
//                $attribute = 'purchase_status_id';
//                $default_status_id = PurchaseInvoiceStatus::firstWhere('default', 1)?->id;
//            } elseif ($item->type === "sales") {
//                $attribute = 'sale_status_id';
//                $default_status_id = SaleInvoiceStatus::firstWhere('default', 1)?->id;
//            } else {
//                throw new \Exception("Unknow invoice type: $item->type");
//            }
//            $item->{$attribute} = $default_status_id; //assigning value
//
//            $item->save();

        });
    }

    public function scopePurchases(Builder $builder)
    {
        return $builder->where('type', self::$TYPE_PURCHASES);
    }

    public function scopeSales(Builder $builder)
    {
        return $builder->where('type', self::$TYPE_SALES);
    }

    /**
     * Sales invoices shown in the sales module (excludes pending order drafts).
     */
    public function scopeListedInSalesModule(Builder $builder): Builder
    {
        return $builder->where('status', '!=', 'sale_order');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvoiceItem::class)->oldest();
    }

    public function additionalCosts(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(AdditionalCost::class, 'item')->oldest();
    }

    public function scopeCurrentClient(Builder $builder)
    {
        return $builder->whereRelation('tenant', 'client_id', get_client()->id);
    }

//    public function payments()
//    {
//        return $this->hasMany(InvoicePayment::class);
//    }

    public function purchasePayments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PaymentVoucherPayment::class, 'model');
    }

    public function salesPayments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ReceiptVoucherPayment::class, 'model');
    }


    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getItemsCost($withAdditionalCosts = false, $applyDiscount = false, $applyTaxes = false)
    {
        $items = 0;
        $extras = 0;
        $services = 0;
        $additionalCosts = 0;
        $taxes = 0;

        foreach ($this->items as $item) {
            $subTotal = $item->price * $item->qty;
            $extras += $item->extras_total;

            if ($applyDiscount) {
                $subTotal -= $item->discount;
            }

            $items += $subTotal;
        }

        if ($withAdditionalCosts) {
            $additionalCosts = $this->getAdditionalCosts(true);
        }

        $services = $this->getServicesCost(true);

        if ($applyTaxes) {
            $taxes = $this->getTaxesAsAmount();
        }

        $taxes = $this->prices_includes_taxes ? 0 : $taxes;
        return $items + $extras + $additionalCosts + $services + $taxes;
    }

    public function getAdditionalCosts($withTaxes = false)
    {
        $total = 0;

        foreach ($this->additionalCosts as $additionalCost) {
            $cost = $additionalCost->cost;
            $tax = 0;
            if ($withTaxes and $additionalCost->tax_profile_data) {
                $total_percentages = collect($additionalCost->tax_profile_data['taxes'] ?? null)->sum('percent');
                if ($total_percentages > 0 and !$this->prices_includes_taxes){
                    $tax = MathService::instance()->getTax($cost, $total_percentages, $this->prices_includes_taxes);
                }
            }
            $total += $cost + $tax;
        }
        return $total;
    }

    public function getDiscountInAmount(): float|int
    {
        return $this->items->sum('discount');
        $discount = 0;

        if ($this->discount_option === "overall") {

            switch ($this->discount_method) {
                case "none":
                {
                    $discount = 0;
                    break;
                }

                case "amount":
                {
                    $discount = $this->discount_amount;
                    break;
                }
                case "percent":
                {
                    $items_cost = 0;
                    foreach ($this->items as $item) {
                        $items_cost += $item->price * $item->qty;
                    }
                    $discount = $items_cost * ($this->discount_percent / 100);
                    break;
                }
                default:
                {
                    throw new \Exception("Unknown discount method: $this->discount_method");
                }
            }
        } elseif ($this->discount_option === "per-item") {
            $discount = $this->items->sum('discount');
        }

        return $discount;
    }

    public function getTaxesAsAmount(): float|int
    {
        $this->loadMissing('items.taxProfile');
        $total = 0;

        foreach ($this->items as $item) {
            if ($item->tax_profile_data) {
                $total_percentages = collect([$item->tax_profile_data])->sum(function ($i) use($item) {
                    return collect($i['taxes'])->sum('percent');
                });
                $subTotal = $item->price * $item->qty;
                $subTotal += $item->extras_total;
                $subTotal -= $item->discount;
                $total += MathService::instance()->getTax($subTotal, $total_percentages, $this->prices_includes_taxes);
            } else {
                $subTotal = $item->price * $item->qty;
                $subTotal -= $item->discount;
                $subTotal += $item->extras_total;
                $taxProfile = $item->taxProfile;
                if ($taxProfile) {
                    $total += MathService::instance()->getTaxFromTaxProfile($subTotal, $taxProfile, $this->prices_includes_taxes);
                }
            }
        }

        foreach ($this->additionalCosts as $item) {
            if ($item->tax_profile_data) {
                $total_percentages = collect([$item->tax_profile_data])->sum(function ($i) {
                    return collect($i['taxes'])->sum('percent');
                });
                $total += MathService::instance()->getTax($item->cost, $total_percentages, $this->prices_includes_taxes);
            } else {
                $taxProfile = $item->taxProfile;
                if ($taxProfile) {
                    $total += MathService::instance()->getTaxFromTaxProfile($item->cost, $taxProfile, $this->prices_includes_taxes);
                }
            }
        }

        foreach ($this->services as $item) {
            if ($item->tax_profile_data) {
                $total_percentages = collect([$item->tax_profile_data])->sum(function ($i) {
                    return collect($i['taxes'])->sum('percent');
                });
                $total += MathService::instance()->getTax($item->price, $total_percentages, $this->prices_includes_taxes);
            } else {
                $taxProfile = $item->taxProfile;
                if ($taxProfile) {
                    $total += MathService::instance()->getTaxFromTaxProfile($item->price, $taxProfile, $this->prices_includes_taxes);
                }
            }
        }

        return $total;
    }

    public function getServicesCost($withTaxes = false)
    {
        $total = 0;

        foreach ($this->services as $service) {
            $price = $service->price;
            $tax = 0;
            if ($withTaxes and $service->tax_profile_data) {
                $total_percentages = collect($service->tax_profile_data['taxes'] ?? null)->sum('percent');
                if ($total_percentages > 0 and !$this->prices_includes_taxes){
                    $tax = MathService::instance()->getTax($price, $total_percentages, $this->prices_includes_taxes);
                }
            }
            $total += $price + $tax;
        }
        return $total;
    }

    public function getTotalPaidPercentAttribute()
    {
        return percent($this->total_paid, $this->getItemsCost(true, true, true));
    }

    public function getTotalPaidAttribute()
    {
        if ($this->type == 'purchases') {
            return $this->purchasePayments->sum('amount');
        }
        return $this->salesPayments->sum('amount');
    }

    public function getTotalUnpaidAttribute()
    {
        if ($this->type == 'purchases') {
            return $this->getItemsCost(true, true, true) - $this->purchasePayments->sum('amount');
        }
        return $this->getItemsCost(true, true, true) - $this->salesPayments->sum('amount');
    }

    public function getSettlementStatusKeyAttribute(): string
    {
        if ($this->paid) {
            $terms = $this->attributes['payment_terms'] ?? 'credit';

            return $terms === 'cash' ? 'cash' : 'paid';
        }

        $payments = $this->type === 'purchases' ? $this->purchasePayments : $this->salesPayments;

        if ($payments->isEmpty()) {
            return 'due';
        }

        return 'partial';
    }

    public function getPaymentStatusAttribute()
    {
        return __('fields.settlement_status_' . $this->settlement_status_key);
    }

    public function getPaymentStatus($local = null)
    {
        return \Illuminate\Support\Facades\Lang::get(
            'fields.settlement_status_' . $this->settlement_status_key,
            [],
            $local ?? app()->getLocale(),
        );
    }

    public function getPaidAttribute(): bool
    {
        return $this->total_paid >= $this->getItemsCost(true, true, true);
    }

    public function isLocked(): bool
    {
        return $this->status === 'cancelled' || $this->locked_at !== null;
    }

    public function isEditable(): bool
    {
        return ! $this->isLocked();
    }

    public function stocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ItemStock::class);
    }

    public function receiptVoucher(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ReceiptVoucher::class, 'invoice_id');
    }

    public function getInvoicePerson()
    {
        $description = "Unknown";

        switch ($this->for) {
            case 'customer':
            {
                return $this->customer->name;
            }

            case 'representative':
            {
                return 'representative';
            }

            case 'supplier':
            {
                if ($this->supplier?->company)
                    $description = $this->supplier?->name . ", " . $this->supplier?->company;
                else
                    $description = $this->supplier?->name;
                break;
            }
        }

        return empty($description) ? "Unknown" : $description;
    }

    public function lockPurchaseInvoice($status)
    {
        if ($this->type != "purchases") {
            throw new \Exception('Invalid invoice type');
        }

        if ($this->items->isEmpty()) {
            throw new \Exception('Unable to lock empty invoice');
        }

        $this->update(
            [
                'status' => $status,
                'locked_by_id' => auth()->id(),
                'locked_at' => now(),
            ]
        );

    }

    public function confirmSalesInvoice(): void
    {
        if ($this->type !== self::$TYPE_SALES) {
            throw new \Exception('Invalid invoice type');
        }

        if ($this->status === 'cancelled') {
            throw new \Exception(__('fields.invoice_locked_statement'));
        }

        $this->loadMissing('items');

        if ($this->items->isEmpty()) {
            throw new \Exception(__('fields.invoice_must_at_least_have_one_product'));
        }

        if ($this->status === 'confirmed' && $this->locked_at !== null) {
            $this->postSalesRevenueJournal();

            return;
        }

        StockService::instance()->takeStockFromSalesInvoice($this);

        $this->update([
            'status' => 'confirmed',
            'locked_by_id' => auth()->id(),
            'locked_at' => now(),
        ]);

        $this->postSalesRevenueJournal();

        InvoicePaymentTermsService::instance()->recordFullCashPayment($this->refresh());
    }

    public function postSalesRevenueJournal(): void
    {
        if ($this->type !== self::$TYPE_SALES) {
            return;
        }

        $this->loadMissing(['customer.acc4', 'items', 'additionalCosts', 'services']);

        $total = (float) $this->getItemsCost(true, true, true);

        if ($total <= 0) {
            return;
        }

        $customerAcc4 = $this->customer?->acc4?->code;

        if (! $customerAcc4) {
            throw new \Exception(__('fields.invoice_payment_missing_customer_account'));
        }

        $tax = round((float) $this->getTaxesAsAmount(), 4);
        $net = round(max(0, $total - $tax), 4);
        $customerName = $this->customer->name;
        $statement = "Sales Invoice: {$customerName}";
        $meta = ['type' => 'sales_invoice', 'id' => $this->id];

        if (! $this->hasSalesRevenueJournal()) {
            $revenueAmount = $tax > 0 ? $net : $total;

            if ($revenueAmount > 0) {
                $op = make_general_voucher_op();

                (new AccountingService())
                    ->setUp(
                        $op->id,
                        $this->date ?? now(),
                        main_currency_iso_code(),
                        generate_double_entry_transaction_id(),
                        $revenueAmount,
                        $this->exchange_rate,
                        $statement,
                        $statement,
                        $this->id,
                        meta: $meta,
                    )
                    ->make($customerAcc4, '121800001')
                    ->finish();
            }
        }

        if ($tax > 0 && ! $this->hasSalesTaxJournal()) {
            if ($this->hasSalesRevenueJournal()) {
                $this->postSalesTaxReclassificationJournal($tax, $meta);
            } else {
                $this->postSalesTaxJournal($customerAcc4, $tax, $statement, $meta);
            }
        }
    }

    protected function postSalesTaxJournal(string $customerAcc4, float $tax, string $statement, array $meta): void
    {
        $taxStatement = "Sales Invoice VAT: {$this->no}";

        $op = make_taxes_op();

        (new AccountingService())
            ->setUp(
                $op->id,
                $this->date ?? now(),
                main_currency_iso_code(),
                generate_double_entry_transaction_id(),
                $tax,
                $this->exchange_rate,
                $taxStatement,
                $statement,
                $this->id,
                meta: $meta,
            )
            ->make($customerAcc4, '122800003')
            ->finish();
    }

    /**
     * Moves VAT out of revenue for invoices that were posted before VAT splitting existed.
     */
    protected function postSalesTaxReclassificationJournal(float $tax, array $meta): void
    {
        $taxStatement = "Sales Invoice VAT: {$this->no}";

        $op = make_taxes_op();

        (new AccountingService())
            ->setUp(
                $op->id,
                $this->date ?? now(),
                main_currency_iso_code(),
                generate_double_entry_transaction_id(),
                $tax,
                $this->exchange_rate,
                $taxStatement,
                $taxStatement,
                $this->id,
                meta: $meta,
            )
            ->make('121800001', '122800003')
            ->finish();
    }

    public function hasSalesTaxJournal(): bool
    {
        $tenantId = $this->tenant_id ?? filament()->getTenant()?->id ?? request()->header('Tenant-Id');

        return DB::table('cash_det')
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('invoice_id', $this->id)
            ->where('account_code', '122800003')
            ->where(function ($query) {
                $query->where('amount_in', '>', 0)
                    ->orWhere('amount_out', '>', 0);
            })
            ->exists();
    }

    public function hasPurchaseTaxJournal(): bool
    {
        $tenantId = $this->tenant_id ?? filament()->getTenant()?->id ?? request()->header('Tenant-Id');

        return DB::table('cash_det')
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('invoice_id', $this->id)
            ->where('account_code', '122800002')
            ->where(function ($query) {
                $query->where('amount_in', '>', 0)
                    ->orWhere('amount_out', '>', 0);
            })
            ->exists();
    }

    public function hasSalesRevenueJournal(): bool
    {
        $tenantId = $this->tenant_id ?? filament()->getTenant()?->id ?? request()->header('Tenant-Id');

        $revenueAccountCodes = DB::table('acc4')
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->whereIn('acc3_code', ['1218', '1219', '1221'])
            ->pluck('code');

        if ($revenueAccountCodes->isEmpty()) {
            return false;
        }

        return DB::table('cash_det')
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('invoice_id', $this->id)
            ->whereIn('account_code', $revenueAccountCodes)
            ->where(function ($query) {
                $query->where('amount_in', '>', 0)
                    ->orWhere('amount_out', '>', 0);
            })
            ->exists();
    }

    public function postPurchaseTaxJournalIfMissing(): void
    {
        if ($this->type !== self::$TYPE_PURCHASES) {
            return;
        }

        $this->loadMissing(['items', 'additionalCosts', 'services']);

        $taxes = round((float) $this->getTaxesAsAmount(), 4);

        if ($taxes <= 0 || $this->hasPurchaseTaxJournal()) {
            return;
        }

        $purchaseMeta = ['type' => 'purchase_invoice', 'id' => $this->id];
        $taxOp = make_taxes_op();

        (new AccountingService())
            ->setUp(
                $taxOp->id,
                $this->date ?? now(),
                main_currency_iso_code(),
                generate_double_entry_transaction_id(),
                $taxes,
                $this->exchange_rate,
                "Purchase Invoice VAT: {$this->no}",
                "Purchase Invoice VAT: {$this->no}",
                $this->id,
                meta: $purchaseMeta,
            )
            ->make('120100001', '122800002')
            ->finish();
    }

    public function confirmPurchaseInvoice(): void
    {
        if ($this->type !== self::$TYPE_PURCHASES) {
            throw new \Exception('Invalid invoice type');
        }

        if ($this->status === 'cancelled') {
            throw new \Exception(__('fields.invoice_locked_statement'));
        }

        $this->loadMissing(['items', 'additionalCosts.type', 'additionalCosts.taxProfile']);

        if ($this->items->isEmpty()) {
            throw new \Exception(__('fields.invoice_must_at_least_have_one_product'));
        }

        if (blank($this->warehouse_id)) {
            throw new \Exception(__('validation.required', ['attribute' => __('fields.warehouse')]));
        }

        if ($this->status === 'confirmed' && $this->locked_at !== null && $this->stocks()->exists()) {
            $this->postPurchaseTaxJournalIfMissing();

            return;
        }

        if ($this->status !== 'confirmed' || $this->locked_at === null) {
            $this->lockPurchaseInvoice('confirmed');
            $this->refresh();
        }

        if (! $this->stocks()->exists()) {
            $this->loadMissing(['items', 'additionalCosts.type', 'additionalCosts.taxProfile']);
            $this->approveAndStockWarehouse();
        }
    }

    public function approveAndStockWarehouse()
    {
        if ($this->type != "purchases") {
            throw new \Exception('Invalid invoice type');
        }

//        $warehouseMethod = "warehouse_for_all_invoice"; // or warehouse_per_invoice_item


        //this will add the stock to the warehouse


        $purchases_amount = 0;

        foreach ($this->items as $invoiceItem) //loop invoice items and make stocks
        {
            $lineSubtotal = $invoiceItem->qty * $invoiceItem->price - $invoiceItem->discount;
            $purchases_amount += $lineSubtotal;

            $item = $invoiceItem->product_variant_id ? ProductVariant::find($invoiceItem->product_variant_id) : Product::find($invoiceItem->product_id);

            ItemStock::create(
                [
                    'tenant_id' => filament()->getTenant()->id ?? request()->header('Tenant-Id'),
                    'type' => 'purchase',
                    'invoice_id' => $this->id,
                    'warehouse_id' => $this->warehouse_id,//->warehouse_id,
                    'product_id' => $invoiceItem->product_id,
                    'item_id' => $item->id,
                    'item_type' => get_class($item),
                    'qty_in' => $invoiceItem->qty,
                    'qty_out' => 0,
                    'unit_cost' => $invoiceItem->price,
                    'date' => now(),
                    'user_id' => auth()->id() ?? auth('sanctum')->id(),
                    'expiration_date' => $invoiceItem->expiration_date,
                ]
            );
        }

        $cat = ExpenseCategory::firstOrCreate(['name' => 'فواتير المشتريات'],
            [
                'tenant_id' => filament()->getTenant()->id ?? request()->header('Tenant-Id'),
                'name' => 'فواتير المشتريات'
            ]);

        $op = make_general_voucher_op();

        $accService = new AccountingService();

        $supplier = Supplier::find($this->supplier_id);

        $accService
            ->setUp(
                $op->id,
                now(),
                main_currency_iso_code(),
                generate_double_entry_transaction_id(),
                $purchases_amount,
                $this->exchange_rate,
                "Purchases from: {$supplier->name}",
                "Purchases from: {$supplier->name}",
                $this->id,
            )->make("120100001", '122600001') //$supplier->acc4->code
            ->finish();

        foreach ($this->additionalCosts as $additionalCost) {

            $cost = (float) $additionalCost->cost;

            if ($cost <= 0) {
                continue;
            }

            $accService
                ->setUp(
                    $op->id,
                    now(),
                    main_currency_iso_code(),
                    generate_double_entry_transaction_id(),
                    $cost,
                    $this->exchange_rate,
                    $additionalCost->statement,
                    $additionalCost->statement,
                    $this->id,
                )->make("120100001", "122300003")
                ->finish();


            //add cost to expenses
            Expense::create([
                'tenant_id' => filament()->getTenant()->id ?? request()->header('Tenant-Id'),
                'credit_acc4_code' => '122600001',
                'debit_acc4_code' => '122300001',
                'expense_category_id' => $cat->id,
                'amount' => $additionalCost->cost,
                'description' => $additionalCost->type->name . " - " . $additionalCost->statement,
                'date' => $this->date,
                'meta' => ['invoice_id' => $this->id, 'invoice_additional_cost_id' => $additionalCost->id]
            ]);
        }

        $this->postPurchaseTaxJournalIfMissing();

    }

    //sales

    public function markPaid()
    {
        if ($this->type != "sales") {
            throw new \Exception('Invalid invoice type');
        }

        $amount_sdg = 0;

        foreach ($this->items as $item) //loop invoice items and make stocks
        {
            $product = $item->product;

            $stocks_ids = $product->takeStock($item->warehouse_id, $item->qty);

            $item->update(
                [
                    'stocks' => $stocks_ids,
                ]
            );

            $amount_sdg += $item->qty * $item->unit_cost_sdg;
        }

        $op = make_general_voucher_op();

        $accService = new AccountingService();

        $this->loadMissing('client.acc4');

        $client = $this->client ?? Client::with('acc4')->firstWhere('name', 'Unknown client');

        abort_if(null == $client, 404, "Failed to process invoice for anonymous client, main account is missing");

        $accService
            ->setUp(
                $op->id,
                now(),
                1,
                generate_double_entry_transaction_id(),
                (int)$amount_sdg,
                $this->exchange_rate,
                "Sales Invoice: {$client->name}",
                "Sales Invoice: {$client->name}",
                $this->id,
            )->make($client->acc4->code, "121800001")
            ->finish();
    }

    public static function dropdownPaid($client_id = null): array
    {
        $invoices = self::with(['items', 'payments'])->get();

        return $invoices->filter(function (Invoice $invoice) use ($client_id) {
            if ($client_id)
                return $invoice->paid == true and $invoice->client_id == $client_id;
            return $invoice->paid === true;
        })->pluck('no', 'id')->toArray();
    }

    public static function dropdownUnpaidForSupplier($supplier_id = null, $existsInPaymentVouchers = true, $except = []): array
    {
        if (! $supplier_id) {
            return [];
        }

        $except = array_values(array_filter((array) $except));

        $invoices = self::query()
            ->purchases()
            ->where('for', 'supplier')
            ->where('supplier_id', $supplier_id)
            ->where('status', '!=', 'cancelled')
            ->with(['items', 'purchasePayments', 'additionalCosts', 'services'])
            ->orderByDesc('date')
            ->get();

        $invoiceIdsWithVoucher = $existsInPaymentVouchers
            ? collect()
            : PaymentVoucher::query()->whereNotNull('invoice_id')->pluck('invoice_id');

        return $invoices
            ->filter(function (Invoice $invoice) use ($except, $existsInPaymentVouchers, $invoiceIdsWithVoucher) {
                if (in_array($invoice->id, $except, true)) {
                    return true;
                }

                if ((float) $invoice->total_unpaid <= 0) {
                    return false;
                }

                if (! $existsInPaymentVouchers && $invoiceIdsWithVoucher->contains($invoice->id)) {
                    return false;
                }

                return true;
            })
            ->mapWithKeys(fn (Invoice $invoice) => [
                $invoice->id => static::formatUnpaidInvoiceDropdownLabel($invoice),
            ])
            ->toArray();
    }

    public static function dropdownUnpaidForCustomer($customer_id = null, $existsInReceiptVouchers = true, $except = []): array
    {
        if (! $customer_id) {
            return [];
        }

        $except = array_values(array_filter((array) $except));

        $invoices = self::query()
            ->sales()
            ->where('for', 'customer')
            ->where('customer_id', $customer_id)
            ->where('status', '!=', 'cancelled')
            ->with(['items', 'salesPayments', 'additionalCosts', 'services'])
            ->orderByDesc('date')
            ->get();

        $invoiceIdsWithVoucher = $existsInReceiptVouchers
            ? collect()
            : ReceiptVoucher::query()->whereNotNull('invoice_id')->pluck('invoice_id');

        return $invoices
            ->filter(function (Invoice $invoice) use ($except, $existsInReceiptVouchers, $invoiceIdsWithVoucher) {
                if (in_array($invoice->id, $except, true)) {
                    return true;
                }

                if ((float) $invoice->total_unpaid <= 0) {
                    return false;
                }

                if (! $existsInReceiptVouchers && $invoiceIdsWithVoucher->contains($invoice->id)) {
                    return false;
                }

                return true;
            })
            ->mapWithKeys(fn (Invoice $invoice) => [
                $invoice->id => static::formatUnpaidInvoiceDropdownLabel($invoice),
            ])
            ->toArray();
    }

    protected static function formatUnpaidInvoiceDropdownLabel(Invoice $invoice): string
    {
        return $invoice->no . ' — ' . format_amount(max(0, (float) $invoice->total_unpaid));
    }

    //start client payments
    //create or edit (if exist) receipt voucher
    public function getReceiptVoucherResourceUrl()
    {
        $rv = ReceiptVoucher::findForInvoice($this->id);

        if ($rv) {
            return ReceiptVoucherResource::getUrl('edit', ['record' => $rv->id]);
        }

        return ReceiptVoucherResource::getUrl('create', ['invoice_id' => $this->id]);
    }

    //start supplier payments
    //create or edit (if exist) payment voucher
    public function getPaymentVoucherResourceUrl()
    {
        $pv = PaymentVoucher::findForInvoice($this->id);

        if ($pv) {
            return PaymentVoucherResource::getUrl('edit', ['record' => $pv->id]);
        }

        return PaymentVoucherResource::getUrl('create', ['invoice_id' => $this->id]);
    }

    public function getDebitAccountCode(): ?string
    {
        if ($this->for === "supplier") {
            return $this->supplier->acc4->code;
        }

        if ($this->for === "customer") {
            return $this->customer->acc4->code;
        }

        return null;
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    public function salesReturns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesReturns::class);
    }

    public function purchasesReturns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchasesReturns::class);
    }

    public function services(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Service::class, 'item');
    }

    public function getUrlAttribute(): string
    {
        return route('public.invoice.show', ['uid' => $this->uid]);
    }

    public function getPdfUrlAttribute(): string
    {
        return route('public.invoice.pdf', ['uid' => $this->uid]);
    }

    //bug
//    public function setStatusAttribute($value)
//    {
//        if ($this->type == "purchases" and !in_array($value, ['purchase_order', 'cancelled', 'confirmed']))
//            throw new \Exception("Invalid status");
//
//        if ($this->type == "sales" and !in_array($value, ['sale_order', 'cancelled', 'confirmed']))
//            throw new \Exception("Invalid status");
//
//        $this->status = $value;
//    }

    public function getServicesCostAttribute()
    {
        return $this->getServicesCost(true);
    }

    public function getAdditionalCostAttribute()
    {
        return $this->getAdditionalCosts(true);
    }

    public function getItemsCostAttribute()
    {
        return $this->getItemsCost(true, true, true);
    }

    public function getExtrasTotalAttribute()
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->extras_total;
        }
        return $total;
    }

}
