<?php

namespace App\Models;

use App\Traits\HasPrefixedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemStock extends BaseModel
{
    use HasFactory, HasPrefixedId;

    protected $guarded = [];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item()
    {
        return $this->morphTo();
    }

    //remove currency later
    public function getTotalCost()
    {
        return ($this->qty_in - $this->qty_out) * $this->unit_cost;
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
