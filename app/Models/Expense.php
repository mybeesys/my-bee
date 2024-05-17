<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Expense extends BaseModel implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime',
        'attributes' => 'array',
        'meta' => 'array',
        'tax_profile_data' => 'array',
    ];

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function taxProfile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }

    public function getAmountFormattedAttribute():string
    {
        return format_amount($this->amount);
    }

    public function getTaxFormattedAttribute():string
    {
        return format_amount($this->tax ?? 0);
    }

    public function getAmountAttribute($value):string
    {
        if($this->tax){
            return $value - $this->tax;
        }
        return $value;
    }

    public function getTotalAttribute():string
    {
        if($this->tax){
            return $this->amount + $this->tax;
        }
        return $this->amount;
    }
}
