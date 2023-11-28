<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductProductionCost extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function productionCost()
    {
        return $this->belongsTo(ProductionCost::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
