<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use App\Traits\HasPrefixedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends BaseModel
{
    use HasFactory, HasFinancialAccount, HasPrefixedId;

    protected $guarded = [];

    public $finance = ['name' => 'name', 'acc3_code' => 1203]; //المدينون العملاء

    public function scopeHideAnonymousClient($query)
    {
        return $query->where('name', '!=', "Unknown client");
    }

    public function getFinanceNameAttribute()
    {
        return $this->name;
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
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

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public static function dropdown(): array
    {
        return Customer::pluck('name', 'id')->toArray();
    }

    public function getLocationAttribute()
    {
        $this->loadMissing('city.state', 'area');

        $location = null;

        if ($this->city)
        {
            $location = $this->city->state->name . " - " . $this->city->name;
        }

        if ($this->area)
            $location = $location . " - " . $this->area->name;

        return $location;
    }

    public function setPhoneAttribute($value)
    {
        return $this->attributes['phone'] = str($value)->remove('+')->value();
    }

}
