<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends BaseModel
{
    use HasFactory, HasFinancialAccount;

    public $finance = ['name' => 'name', 'acc3_code' => 1217];

    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
    ];
}
