<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends BaseModel
{
    use HasFactory, HasFinancialAccount;

    protected $guarded = [];

    protected $casts = ['tax_profile_data' => 'array'];

    public $finance = ['name' => 'name', 'acc3_code' => 1204]; //المخزون

    public function getFinanceAttributes(): array
    {
        return ['name' => $this->type->name, 'acc3_code' => 1204];
    }

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }

    public function taxProfile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TaxProfile::class, 'tax_profile_id');
    }

    public function item(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
