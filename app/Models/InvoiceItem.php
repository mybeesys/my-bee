<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'stocks' => 'array',
        'inventory_taken_from_warehouses' => 'array',
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
        'tax_profile_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function taxProfile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }

    public function orderDetails(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_details_id');
    }

    public function salesReturnsDetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesReturnsDetails::class);
    }

    public function purchasesReturnsDetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchasesReturnsDetails::class);
    }

    public function extras(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvoiceItemExtra::class, 'invoice_item_id');
    }

    public function getTaxesAsAmount(): float|int
    {
        $total = 0;

        //for sale invoices calc tax from saved tax profile data
        if ($this->orderDetails?->tax_profile_data) {
            $total_percentages = collect([$this->orderDetails->tax_profile_data])->sum(function ($i) {
                return collect($i['taxes'])->sum('percent');
            });

            $subTotal = $this->price * $this->qty;
            $subTotal -= $this->discount;
            $total += $subTotal * ($total_percentages / 100);

        } else {
            $subTotal = $this->price * $this->qty;
            $subTotal -= $this->discount;

            $taxProfile = $this->taxProfile;
            if ($taxProfile) {
                $total += $subTotal * ($taxProfile->total_percentages / 100);
            }
        }

        return $total;
    }

    public function getSubTotalAttribute()
    {
        return $this->price * $this->qty + $this->tax - $this->discount;
    }

    public function getQtyAttribute($value)
    {
        $this->loadMissing(['purchasesReturnsDetails', 'salesReturnsDetails']);

        $returned = $this->purchasesReturnsDetails->sum('qty');
        $returned += $this->salesReturnsDetails->sum('qty');

        return $value - $returned;
    }

    public function getQtyReturnedAttribute()
    {
        $this->loadMissing(['purchasesReturnsDetails', 'salesReturnsDetails']);

        $returned = $this->purchasesReturnsDetails->sum('qty');
        $returned += $this->salesReturnsDetails->sum('qty');

        return $returned;
    }

//    public function getNameAttribute()
//    {
//        if ($this->productVariant) {
//            return $this->productVariant->name;
//        }
//        if ($this->product) {
//            return $this->product->name;
//        }
//        return "N/A";
//    }
}
