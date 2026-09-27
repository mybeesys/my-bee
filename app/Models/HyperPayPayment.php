<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HyperPayPayment extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CHECKOUT = 'checkout';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING_GATEWAY = 'pending_gateway';

    public const SOURCE_REGISTRATION = 'registration';

    public const SOURCE_SUBSCRIPTION = 'subscription';

    protected $table = 'hyperpay_payments';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'billing' => 'array',
        'request_payload' => 'array',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (blank($payment->uid)) {
                $payment->uid = strtoupper(Str::random(16));
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function platformCoupon(): BelongsTo
    {
        return $this->belongsTo(PlatformCoupon::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CHECKOUT, self::STATUS_PENDING_GATEWAY], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => __('fields.hyperpay_status_paid'),
            self::STATUS_FAILED => __('fields.hyperpay_status_failed'),
            self::STATUS_CHECKOUT => __('fields.hyperpay_status_checkout'),
            self::STATUS_PENDING_GATEWAY => __('fields.hyperpay_status_pending_gateway'),
            default => __('fields.hyperpay_status_pending'),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_PENDING_GATEWAY => 'warning',
            self::STATUS_CHECKOUT => 'info',
            default => 'gray',
        };
    }
}
