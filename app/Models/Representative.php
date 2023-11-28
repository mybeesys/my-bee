<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Representative extends BaseModel
{
    use HasFactory, HasFinancialAccount;

    protected $guarded = [];

    public $finance = ['name' => 'name', 'acc3_code' => 1213]; //المناديب

    public function getFinanceNameAttribute()
    {
        return $this->name;
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}
