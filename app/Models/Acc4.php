<?php

namespace App\Models;

use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Acc4 extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    protected $table = "acc4";

    protected $primaryKey = 'code';

    public function getFullNameAttribute()
    {
        return $this->name . ' - ' . $this->code;
    }

    public function acc3(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Acc3::class, 'acc3_code');
    }

    public function item()
    {
        return $this->morphTo();
    }

    public function scopeProduct(Builder $query)
    {
        return $query->where('item_type', Product::class);
    }

    public static function inventoryItemClasses(): array
    {
        return [
            Product::class,
            ProductVariant::class,
            ProductExtra::class,
            ProductUnit::class,
        ];
    }

    public function scopeExcludeInventoryItems(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('item_type')
                ->orWhereNotIn('item_type', static::inventoryItemClasses());
        });
    }

    public static function systemAccountCodes(): array
    {
        return [
            '120100001',
            '121800001',
            '121900001',
            '122600001',
            '122300001',
            '122300002',
            '122300003',
            '122500001',
            '122500002',
            '122700001',
            '122100001',
            '122800001',
            '122800002',
            '122800003',
        ];
    }

    public function scopeOtherPartyAccounts(Builder $query): Builder
    {
        return $query
            ->whereNull('item_type')
            ->whereNotIn('code', static::systemAccountCodes());
    }

    public function scopeBankAccounts(Builder $query): Builder
    {
        return $query
            ->whereNull('item_type')
            ->where('acc3_code', '1227');
    }

    public function isBankAccount(): bool
    {
        return $this->item_type === null && (string) $this->acc3_code === '1227';
    }

    public function isSystemAccount(): bool
    {
        return in_array((string) $this->code, static::systemAccountCodes(), true);
    }

    public function isOtherPartyAccount(): bool
    {
        return $this->item_type === null && ! $this->isSystemAccount();
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashDet::class, 'account_code', 'code');
    }

    public function hasTransactions(): bool
    {
        return $this->cashMovements()->exists();
    }

    public function canBeEdited(): bool
    {
        return $this->isOtherPartyAccount() || $this->isBankAccount() || $this->editable;
    }

    public function canBeDeleted(): bool
    {
        if ($this->isSystemAccount()) {
            return false;
        }

        return $this->canBeEdited() && ! $this->hasTransactions();
    }

    public static function nextCodeForAcc3(string $acc3Code): string
    {
        $last = static::query()
            ->where('code', 'like', $acc3Code.'%')
            ->orderByDesc('code')
            ->value('code');

        return $last === null ? $acc3Code.'000001' : (string) ((int) $last + 1);
    }

    public function scopeOnlyInventoryItems(Builder $query): Builder
    {
        return $query->whereIn('item_type', static::inventoryItemClasses());
    }

    public function isInventoryItemAccount(): bool
    {
        return in_array($this->item_type, static::inventoryItemClasses(), true);
    }

    public function prices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ItemPrice::class, 'acc4_code');
    }

    public function lastPrice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ItemPrice::class, 'acc4_code')->latest();
    }

    public function getItemPricePriceForUnit($unit_id, $getPrice = false): mixed
    {
        $this->loadMissing('prices');

        if ($getPrice)
            return $this->prices->where('unit_id', $unit_id)->last()?->price;

        return $this->prices->where('unit_id', $unit_id)->last();
    }

    public static function asOptions(array $only_item_class = [], array $exclude_item_class = [], $useItemId = false, $with_code = false)
    {
        $options = [];

        $query = self::query()->with('item');

        if (count($only_item_class) > 0) {
            $query->whereIn('item_type', $only_item_class);
        }
        if (count($exclude_item_class) > 0) {
            $query->whereNotIn('item_type', $exclude_item_class)->orWhereNull('item_type');
        }

        $data = $query->get();

        foreach ($data as $acc4) {
            if ($acc4->item) {
                $id = $useItemId ? $acc4->item->id : $acc4->code;
                $name = $with_code ? $acc4->item->finance_name . " - $acc4->code" : $acc4->item->finance_name;
                $options[$id] = $name ?? "N/A";
            } else {
                if (!$useItemId){
                    $name = $with_code ? $acc4->name . " - $acc4->code" : $acc4->name;
                    $options[$acc4->code] = $name ?? "N/A";;
                }
            }
        }

        return $options;
    }

}
