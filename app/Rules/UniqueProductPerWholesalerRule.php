<?php

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

class UniqueProductPerWholesalerRule implements ValidationRule, DataAwareRule
{
    public const BARCODE = 'barcode';

    public const ACCOUNTING_CODE = 'accounting_code';

    protected array $data = [];

    public function __construct(
        protected string $column = self::BARCODE,
        protected mixed $ignoreId = null,
        protected mixed $wholesalerId = null,
    ) {
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $wholesalerId = $this->resolveWholesalerId();

        if (blank($wholesalerId)) {
            return;
        }

        $tenantId = filament()->getTenant()?->id ?? request()->header('Tenant-Id');

        $query = Product::query()
            ->where('supplier_id', $wholesalerId)
            ->when(filled($tenantId), fn (Builder $builder) => $builder->where('tenant_id', $tenantId));

        if ($this->column === self::ACCOUNTING_CODE) {
            $query->whereHas('acc4', fn (Builder $builder) => $builder->where('code', $value));
        } else {
            $query->where($this->column, $value);
        }

        if (filled($this->ignoreId)) {
            $query->where('id', '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail('validation.unique_product_per_wholesaler')->translate([
                'attribute' => __('fields.'.$this->fieldKey()),
            ]);
        }
    }

    protected function resolveWholesalerId(): mixed
    {
        if (filled($this->wholesalerId)) {
            return $this->wholesalerId;
        }

        if (filled(data_get($this->data, 'supplier_id'))) {
            return data_get($this->data, 'supplier_id');
        }

        if (filled($this->ignoreId)) {
            return Product::query()->find($this->ignoreId)?->supplier_id;
        }

        return null;
    }

    protected function fieldKey(): string
    {
        return $this->column === self::ACCOUNTING_CODE ? 'accounting_code' : $this->column;
    }
}
