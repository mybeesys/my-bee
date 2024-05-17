<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxProfile extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $with = ['taxes'];

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

    public static function asOptions()
    {
        $options = [];

        $data = TaxProfile::with('taxes')
            ->has('taxes')
            ->get();

        foreach ($data as $item)
        {
            $options[$item->id] = $item->name . " (" . $item->total_percentages . "%)";
        }
        return $options;
    }
}
