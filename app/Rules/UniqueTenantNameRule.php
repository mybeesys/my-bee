<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\TenantNamingService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueTenantNameRule implements ValidationRule
{
    public function __construct(protected ?int $exceptTenantId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || blank($value)) {
            return;
        }

        if (! TenantNamingService::instance()->nameExists($value, $this->exceptTenantId)) {
            return;
        }

        $suggestion = TenantNamingService::instance()->suggestUniqueName($value);

        $fail(__('fields.tenant_name_taken', ['suggestion' => $suggestion]));
    }
}
