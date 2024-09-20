<?php

namespace App\Models;

use App\Traits\HasPrefixedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplyOrder extends BaseModel
{
    use HasFactory, HasPrefixedId;

    protected $guarded = [];

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SupplyOrderDetails::class);
    }

    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getUrlAttribute()
    {
        return config('app.shop_url') . \Filament\Facades\Filament::getTenant()->slug . "/supply-orders/" . $this->no;
    }
}
