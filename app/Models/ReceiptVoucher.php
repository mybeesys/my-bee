<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptVoucher extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime',
        'files' => 'array',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReceiptVoucherPayment::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acc4(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Acc4::class);
    }

    public static function findForInvoice(int $invoiceId): ?self
    {
        $direct = static::query()->where('invoice_id', $invoiceId)->first();

        if ($direct) {
            return $direct;
        }

        return static::query()
            ->whereHas('payments', fn ($query) => $query
                ->where('model_type', Invoice::class)
                ->where('model_id', $invoiceId))
            ->first();
    }
}
