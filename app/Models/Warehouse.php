<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;

    class Warehouse extends BaseModel
    {
        use HasFactory;

        protected $guarded = [];

        protected $casts = [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];

        public function scopeHasProduct(Builder $query, $product_id)
        {
            return $query->with('stocks')
                ->whereHas('stocks', function (Builder $q) use ($product_id) {
                    return $q->where('item_type', Product::class)
                        ->where('item_id', $product_id)
                        ->whereRaw("qty_in - qty_out > 0 order by greatest(qty_in - qty_out, 0)");
                });
        }

        public function stocks()
        {
            return $this->hasMany(ItemStock::class);
        }

    }
