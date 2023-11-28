<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxProfile extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function taxes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function getTotalPercentagesAttribute(): int
    {
        $this->loadMissing('taxes');
        return $this->taxes->sum('percent');
    }

    public function getTaxesDescriptionAttribute(): string
    {
        $this->loadMissing('taxes');
        $descriptions = [];
        foreach ($this->taxes as $tax) {
            $descriptions[] = "$tax->percent%  $tax->description";
        }
        return implode(', ', $descriptions);
    }
}
