<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformCoupon extends BaseModel
{
    use HasFactory;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'value' => 'float',
        'valid_until' => 'datetime',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(PlatformCouponRedemption::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeValid($query)
    {
        return $query
            ->active()
            ->where(function ($builder) {
                $builder
                    ->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            });
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    public function isPercent(): bool
    {
        return $this->type === self::TYPE_PERCENT;
    }

    public function isFixed(): bool
    {
        return $this->type === self::TYPE_FIXED;
    }
}
