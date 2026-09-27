<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $with = ['subscription.plan'];

    //companies
    public function tenants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class)->latest();
    }

    public function subscriptionRenewalRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubscriptionRenewalRequest::class)->latest();
    }

    public function hyperPayPayments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HyperPayPayment::class)->latest();
    }

    public function pendingPlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    public function hasPendingPaidRegistration(): bool
    {
        return filled($this->pending_plan_id);
    }

    public function clearPendingPaidRegistration(): void
    {
        if (! $this->hasPendingPaidRegistration()) {
            return;
        }

        $this->forceFill([
            'pending_plan_id' => null,
            'pending_billing_period' => null,
        ])->save();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setPhoneAttribute($value)
    {
        if ($value === null || $value === '') {
            $this->attributes['phone'] = null;

            return;
        }

        $this->attributes['phone'] = str($value)->remove('+')->value();
    }

    public function setMobileAttribute($value)
    {
        if ($value === null || $value === '') {
            $this->attributes['mobile'] = null;

            return;
        }

        $this->attributes['mobile'] = str($value)->remove('+')->value();
    }

}
