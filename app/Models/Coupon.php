<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Support\Collection;

    class Coupon extends BaseModel
    {
        use HasFactory;

        protected $guarded = [];

        protected $casts = [
            'id' => 'integer',
            'active' => 'boolean',
            'value' => 'integer',
            'valid_until' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];

        public static string $TYPE_FIXED = "fixed";
        public static string $TYPE_PERCENT = "percent";

        public static string $FOR_ANY_CUSTOMER = "any-customer";
        public static string $FOR_SPECIFIED_CUSTOMERS = "specified-customers";

        public function scopeActive($query)
        {
            return parent::scopeActive($query);
        }

        public function scopeValid($query)
        {
            return $query->where('active', 1)->whereDate('valid_until', '>', now()->toDateTime());
        }

        public function scopeInactive($query)
        {
            return parent::scopeInactive($query);
        }

        public function scopeCode($query)
        {
            return parent::scopeActive($query);
        }

        public function usages(): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            return $this->hasMany(Order::class, 'coupon_id');
        }

        public function getStatusAttribute()
        {
            if ($this->active === false) {
                return "Inactive";
            } elseif ($this->valid_until->isPast()) {
                return "Expired";
            }elseif (!$this->userCanUse()) {
                return "Permission denied";
            } elseif ($this->isUsedByCustomer()) {
                return "Used";
            }

            return "Accepted";
        }

        public function getDiscountAttribute()
        {
            if ($this->type === self::$TYPE_FIXED) {
                return number_format($this->value, 2) . " SDG";
            } elseif ($this->type === self::$TYPE_PERCENT) {
                return $this->value . "%";
            }
            return $this->value;
        }
    }
