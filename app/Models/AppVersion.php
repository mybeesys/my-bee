<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'version_code' => 'integer',
        'update_summary' => 'array',
        'must_update' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
