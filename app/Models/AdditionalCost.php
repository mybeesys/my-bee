<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdditionalCost extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'tax_profile_data' => 'array',
    ];

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AdditionalCostType::class, 'additional_cost_type_id');
    }

    public function item(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function taxProfile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TaxProfile::class, 'tax_profile_id');
    }

}
