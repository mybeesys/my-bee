<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturns extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesReturnsDetails::class, 'sales_returns_id');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isCustomerReturn(): bool
    {
        return filled($this->customer_id) && blank($this->invoice_id);
    }

    public function resolveCustomer(): ?Customer
    {
        if ($this->relationLoaded('customer') && $this->customer) {
            return $this->customer;
        }

        $this->loadMissing('customer', 'invoice.customer');

        return $this->customer ?? $this->invoice?->customer;
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
