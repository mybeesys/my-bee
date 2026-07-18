<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchasesReturns extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchasesReturnsDetails::class, 'purchases_returns_id');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isSupplierReturn(): bool
    {
        return filled($this->supplier_id) && blank($this->invoice_id);
    }

    public function resolveSupplier(): ?Supplier
    {
        $this->loadMissing('supplier', 'invoice.supplier');

        return $this->supplier ?? $this->invoice?->supplier;
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
