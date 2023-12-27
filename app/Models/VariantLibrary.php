<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariantLibrary extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function options(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VariantLibraryOption::class)->orderByDesc('sort');
    }

    public function getNameAttribute()
    {
        if (app()->getLocale() == "ar")
            return $this->name_ar;

        return $this->name_en;
    }
}
