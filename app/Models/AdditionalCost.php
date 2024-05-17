<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdditionalCost extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AdditionalCostType::class, 'additional_cost_type_id');
    }

    public function item(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
