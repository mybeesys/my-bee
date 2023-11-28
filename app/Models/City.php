<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Translatable\HasTranslations;

class City extends BaseModel
{
    use HasFactory, HasTranslations;

    protected $guarded = [];

    public $translatable = ['name'];

}
