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
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
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

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function item()
    {
        return $this->morphTo();
    }

    public function taxProfile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }

}
