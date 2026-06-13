<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends BaseModel
{
    use HasFactory, HasFinancialAccount;

    public $finance = ['name' => 'name', 'acc3_code' => 1224]; //مصروفات توصيل الطلبات

    protected $guarded = [];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public static function getDefault()
    {
        return self::firstOrCreate(
            [
                'name' => 'Driver',
            ],
            [
                'name' => 'Driver',
                'phone' => "999999999",
            ]
        );
    }

    public static function dropdown()
    {
        self::getDefault(); //create if not exists

        return self::pluck('name', 'id');
    }

}
