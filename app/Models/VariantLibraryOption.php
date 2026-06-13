<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariantLibraryOption extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function library(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VariantLibrary::class);
    }

    public function getNameAttribute()
    {
        if (app()->getLocale() == "ar")
            return $this->name_ar;

        return $this->name_en;
    }
}
