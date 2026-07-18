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

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function getLocationAttribute()
    {
        $this->loadMissing('city.state', 'area');

        $location = null;

        if ($this->city) {
            $location = $this->city->state->name . ' - ' . $this->city->name;
        }

        if ($this->area) {
            $location = $location . ' - ' . $this->area->name;
        }

        return $location;
    }

    public function setPhoneAttribute($value)
    {
        return $this->attributes['phone'] = str($value)->remove('+')->value();
    }

}
