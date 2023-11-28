<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class InvoiceStatus extends BaseModel
{
    use HasFactory, HasTranslations;

    protected $guarded = [];

    public $translatable = ['name'];
}
