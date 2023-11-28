<?php

namespace App\Models;

use App\Services\AccountingService;
use Dompdf\Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Invoice extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'files' => 'array',
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
            if ($item->type === "purchases") {
                $attribute = 'purchase_status_id';
                $default_status_id = PurchaseInvoiceStatus::firstWhere('default', 1)?->id;
            } elseif ($item->type === "sales") {
                $attribute = 'sale_status_id';
                $default_status_id = SaleInvoiceStatus::firstWhere('default', 1)?->id;
            } else {
                throw new \Exception("Unknow invoice type: $item->type");
            }
            $item->{$attribute} = $default_status_id; //assigning value

            $item->save();
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


    public function purchaseStatus(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceStatus::class, 'purchase_status_id');
    }

    public function saleStatus(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SaleInvoiceStatus::class, 'sale_status_id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function additionalCosts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvoiceAdditionalCost::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getItemsCost($withAdditionalCosts = false)
    {
        $items_cost = 0;

        foreach ($this->items as $item) {
            $items_cost += $item->price * $item->qty;
        }

        if ($withAdditionalCosts)
            return $items_cost + $this->getAdditionalCosts() - $this->getDiscountInAmount();

        return $items_cost - $this->getDiscountInAmount();
    }

    public function getAdditionalCosts(): float
    {
        $total = 0;
        foreach ($this->additionalCosts as $item) {
            $total += $item->cost;
        }
        return $total;
    }

    public function getDiscountInAmount(): float|int
    {
        $discount = null;

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

    public function getTotalPaidAttribute()
    {
        $amount_sdg = 0;
        $amount_usd = 0;

        foreach ($this->payments as $payment) {
            if ($payment->currency->iso_code == "SDG") //sdg
            {
                $amount_sdg += $payment->amount;
            } else if ($payment->currency->iso_code == "USD") { //usd
                $amount_usd += $payment->amount;
                $amount_sdg += floatval($payment->amount * $payment->exchange_rate);
            } else {
                throw new \Exception('getTotalPaid: Currency not supported');
            }
        }
        return [
            'sdg' => $amount_sdg,
            'usd' => $amount_usd,
        ];
    }

    public function getPaidAttribute(): bool
    {
        return $this->getItemsCost() == $this->total_paid['sdg'];
    }

    protected function checkCurrencySupport($currency)
    {
        if (!in_array($currency, ['SDG', 'USD']))
            throw new \Exception("Unsupported currency ($currency)");
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
                break;
            }

            case 'representative':
            {
                break;
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

    public function lockPurchaseInvoice($status_id)
    {
        if ($this->type != "purchases") {
            throw new \Exception('Invalid invoice type');
        }

        if ($this->items->isEmpty()) {
            throw new \Exception('Unable to lock empty invoice');
        }

        //this will add the stock to the warehouse
        $purchases_amount = 0;

        foreach ($this->items as $item) //loop invoice items and make stocks
        {
            $purchases_amount += $item->qty * $item->price;
        }

        $purchases_amount = $purchases_amount - $this->getDiscountInAmount();

        $op = make_general_voucher_op();

        $accService = new AccountingService();

        $supplier = Supplier::find($this->supplier_id);

        foreach ($this->additionalCosts as $additionalCost) {
            $accService
                ->setUp(
                    $op->id,
                    now(),
                    main_currency_iso_code(),
                    generate_double_entry_transaction_id(),
                    $additionalCost->cost,
                    $this->exchange_rate,
                    $additionalCost->statement,
                    $additionalCost->statement,
                    $this->id,
                )->make("122600002", null)
                ->finish();
        }
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
            )->make("122600001", $supplier->acc4->code)
            ->finish();


        $this->update(
            [
                'purchase_status_id' => $status_id,
                'locked_by_id' => auth()->id(),
                'locked_at' => now(),
            ]
        );

    }

    public function approveAndStockWarehouse()
    {
        if ($this->type != "purchases") {
            throw new \Exception('Invalid invoice type');
        }


        //this will add the stock to the warehouse
        $purchases_amount_sdg = 0;

        foreach ($this->items as $item) //loop invoice items and make stocks
        {

            $purchases_amount_sdg += $item->qty * $item->price_sdg;

            $product = $item->product;

            ItemStock::create(
                [
                    'type' => 'purchase',
                    'invoice_id' => $this->id,
                    'warehouse_id' => $item->warehouse_id,
                    'item_id' => $product->id,
                    'item_type' => Product::class,
                    'date' => now(),
                    'user_id' => auth()->id(),
                    'qty_in' => $item->qty,
                    'qty_out' => 0,
                    'currency_iso_code' => setting('main_currency', 'SAR'),
                    'unit_cost_sdg' => $item->price_sdg,
                    'unit_cost_usd' => $item->price_usd,
                    'expiration_date' => $item->expiration_date,
                ]
            );
        }

        $op = make_general_voucher_op();

        $accService = new AccountingService();

        $supplier = Supplier::find($this->supplier_id);

        foreach ($this->additionalCosts as $additionalCost) {
            $accService
                ->setUp(
                    $op->id,
                    now(),
                    1,
                    generate_double_entry_transaction_id(),
                    $additionalCost->cost_sdg,
                    $this->exchange_rate,
                    $additionalCost->statement,
                    $additionalCost->statement,
                    $this->id,
                )->make("122600002", null)
                ->finish();
        }

        $accService
            ->setUp(
                $op->id,
                now(),
                1,
                generate_double_entry_transaction_id(),
                $purchases_amount_sdg,
                $this->exchange_rate,
                "Purchases from: {$supplier->name}",
                "Purchases from: {$supplier->name}",
                $this->id,
            )->make("122600001", $supplier->acc4->code)
            ->finish();

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

    public static function dropdownUnpaid($client_id = null, $existsInReceiptVouchers = true, $except = []): array
    {
        $invoices = self::with(['items', 'payments'])->get();

        $unpaid = $invoices->filter(function (Invoice $invoice) use ($client_id, $except) {
            if ($client_id)
                return ($invoice->paid == false and $invoice->client_id == $client_id) or ($invoice->client_id == $client_id and in_array($invoice->id, $except));
            return $invoice->paid === false;
        });

        if (!$existsInReceiptVouchers) {
            $invoice_ids_in_receipts_vouchers = ReceiptVoucher::pluck('invoice_id')->toArray();

            return $unpaid->whereNotIn('id', $invoice_ids_in_receipts_vouchers)->pluck('no', 'id')->toArray();

        }
        return $unpaid->pluck('no', 'id')->toArray();
    }
}
