<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function supplyOrders(): HasMany
    {
        return $this->hasMany(SupplyOrder::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'supplier_id')
            ->where('type', 'purchases')
            ->where('temp', false);
    }

}
