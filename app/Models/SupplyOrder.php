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

    public function getUrlAttribute(): string
    {
        $this->loadMissing('tenant');

        return route('public.supply-order.show', [
            'slug' => $this->tenant->slug,
            'no' => $this->no,
        ]);
    }
}
