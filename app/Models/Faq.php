<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

}
