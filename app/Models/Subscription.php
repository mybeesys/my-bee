<?php

namespace App\Models;

use App\Services\SubscriptionCouponService;
use App\Services\SubscriptionPricingService;
use App\Traits\HasPrefixedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Subscription extends BaseModel
{
    use HasFactory, HasPrefixedId;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'datetime',
        'paid_at' => 'datetime',
        'price' => 'float',
        'price_ex_tax' => 'float',
        'tax_amount' => 'float',
        'tax_percent' => 'float',
        'discount_amount' => 'float',
    ];

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function platformCoupon(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PlatformCoupon::class);
    }

    public function ensurePublicUid(): string
    {
        if (! Schema::hasColumn($this->getTable(), 'uid')) {
            throw new \RuntimeException('Run migrations: subscriptions.uid column is missing.');
        }

        if (filled($this->uid)) {
            return (string) $this->uid;
        }

        do {
            $uid = Str::upper(Str::random(12));
        } while (static::query()->where('uid', $uid)->exists());

        $this->forceFill(['uid' => $uid])->saveQuietly();

        return $uid;
    }

    public function getUrlAttribute(): string
    {
        return route('public.subscription-invoice.show', ['uid' => $this->ensurePublicUid()]);
    }

    public function getInvoiceNoAttribute(): string
    {
        return 'SUB-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function isFree(): bool
    {
        return (float) ($this->price ?? 0) <= 0.0;
    }

    public static function isSubscribedTo($plan_id, Client $client): bool
    {
        $client->loadMissing(['user', 'subscription.plan']);

        if (!$client->user->hasRole(User::ROLE_CLIENT)) {
            throw new \Exception("Client is not valid for subscription.");
        }

        return $client->subscriptions->where('plan_id', $plan_id)->first() !== null;
    }

    public static function subscribe(
        Plan $plan,
        Client $client,
        string $billingPeriod = SubscriptionPricingService::BILLING_MONTHLY,
        ?PlatformCoupon $coupon = null
    ): Subscription {
        $client->loadMissing(['user', 'subscription.plan']);

        if (!$client->user->hasRole(User::ROLE_CLIENT)) {
            throw new \Exception("Client is not valid for subscription.");
        }

        $quote = SubscriptionPricingService::instance()->quote($plan, $billingPeriod);
        $discountAmount = 0.0;

        if ($coupon) {
            // Re-validate at subscribe time (race / reuse).
            $coupon = SubscriptionCouponService::instance()->findUsable($coupon->code, $client);
            $quote = SubscriptionCouponService::instance()->applyToQuote($quote, $coupon);
            $discountAmount = (float) ($quote['discount_amount'] ?? 0);
        }

        return DB::transaction(function () use ($plan, $client, $quote, $coupon, $discountAmount) {
            $subscription = Subscription::create([
                'plan_id' => $plan->id,
                'client_id' => $client->id,
                'start_date' => now(),
                'price' => $quote['total_inc_tax'],
                'billing_period' => $quote['billing_period'],
                'price_ex_tax' => $quote['subtotal_ex_tax'],
                'tax_amount' => $quote['tax_amount'],
                'tax_percent' => $quote['tax_percent'],
                'platform_coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'discount_amount' => $coupon ? $discountAmount : null,
            ]);

            if ($coupon) {
                PlatformCouponRedemption::create([
                    'platform_coupon_id' => $coupon->id,
                    'client_id' => $client->id,
                    'subscription_id' => $subscription->id,
                ]);
            }

            $client->refresh();

            return $client->subscription;
        });
    }
}
