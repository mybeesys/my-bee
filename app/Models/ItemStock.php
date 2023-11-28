<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemStock extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item()
    {
        return $this->morphTo();
    }

    //remove currency later
    public function getTotalCost($currency_iso_code = 'SAR')
    {
        if ($this->currency_iso_code = $currency_iso_code)
            return ($this->qty_in - $this->qty_out) * $this->unit_cost;

        return 0;
//            if ($currency == 'sdg')
//                return ($this->qty_in - $this->qty_out) * $this->unit_cost_sdg;
//
//            return ($this->qty_in - $this->qty_out) * $this->unit_cost_usd;
    }

    public function getAvailableAttribute(): int
    {
        return $this->qty_in - $this->qty_out - $this->qty_moved;
    }

    public function stock(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'stock_id');
    }

}
