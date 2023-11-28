<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends BaseModel
{
    use HasFactory, HasFinancialAccount;

    protected $guarded = [];

    protected $casts = [
        "created_at" => 'datetime',
        "updated_at" => 'datetime',
    ];

    public $finance = ['name' => 'name', 'acc3_code' => 1214]; //الدائنون (الموردون)

    public function getFinanceNameAttribute()
    {
        return $this->name;
    }

}
