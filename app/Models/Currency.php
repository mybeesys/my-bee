<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public static function asSelect()
    {
        $currencies = self::get();
        $data = [];
        foreach ($currencies as $currency)
        {
            $data[$currency->id] = $currency->name . " ($currency->iso_code) " . " ($currency->symbol_native)";
        }
        return $data;
    }
}
