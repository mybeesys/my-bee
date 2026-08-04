<?php

namespace App\Services;

use App\Filament\Tenant\Concerns\InteractsWithInvoiceReturnLineItems;

/**
 * Thin wrapper so API/services can call shared return logic from the Filament trait
 * without modifying web pages.
 */
class SalesReturnWorkflow
{
    use InteractsWithInvoiceReturnLineItems;

    public static function normalizeDetailForSave(array $data): array
    {
        return static::normalizeReturnDetailForSave($data);
    }

    public static function normalizeCreditAmount(mixed $value): float
    {
        return static::normalizeReturnCreditPaymentAmount($value);
    }
}
