<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionRenewalRequest extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'quoted_price_ex_tax' => 'float',
        'quoted_tax_amount' => 'float',
        'quoted_tax_percent' => 'float',
        'quoted_discount_amount' => 'float',
        'quoted_total' => 'float',
        'cancelled_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    public function platformCoupon(): BelongsTo
    {
        return $this->belongsTo(PlatformCoupon::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => __('fields.subscription_request_status_active'),
            self::STATUS_CANCELLED => __('fields.subscription_request_status_cancelled'),
            default => __('fields.subscription_request_status_pending'),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_CANCELLED => 'danger',
            default => 'warning',
        };
    }
}
