<?php

namespace App\Rules;

use App\Models\Acc4;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueAcc4OtherPartyNameRule implements ValidationRule
{
    public function __construct(protected mixed $ignoreCode = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tenantId = filament()->getTenant()?->id ?? request()->header('Tenant-Id');

        if (! $tenantId) {
            return;
        }

        $query = Acc4::query()
            ->userCreatedOtherPartyAccounts()
            ->where('tenant_id', $tenantId)
            ->where('name', $value);

        if (filled($this->ignoreCode)) {
            $query->where('code', '!=', $this->ignoreCode);
        }

        if ($query->exists()) {
            $fail('validation.unique')->translate(['attribute' => __('fields.name')]);
        }
    }
}
