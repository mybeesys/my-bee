<?php

namespace App\Services;

use App\Models\CashDet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CashDetReportQueryService
{
    /**
     * @param  callable(Builder): Builder  $accountScope
     */
    public function query(Request $request, callable $accountScope, bool $requireAccountCode = false): Builder
    {
        $query = CashDet::query()
            ->with(['account', 'operation', 'account.acc3', 'currency', 'invoice'])
            ->whereHas('account', $accountScope)
            ->orderByDesc('id');

        if ($requireAccountCode && blank($request->input('account_code'))) {
            return $query->whereRaw('0 = 1');
        }

        return $this->applyCommonFilters($query, $request);
    }

    public function applyCommonFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when(
                $request->filled('account_code'),
                fn (Builder $builder) => $builder->where('account_code', $request->input('account_code'))
            )
            ->when(
                $request->filled('op_id'),
                fn (Builder $builder) => $builder->where('op_id', $request->input('op_id'))
            )
            ->when(
                $request->filled('transaction_id'),
                fn (Builder $builder) => $builder->where('transaction_id', $request->input('transaction_id'))
            )
            ->when(
                $request->filled('from_date') || $request->filled('to_date'),
                function (Builder $builder) use ($request) {
                    // Match Filament CashDet reports: filter on created_at (transaction display still uses date).
                    return $builder->whereDateBetween(
                        'created_at',
                        $request->input('from_date'),
                        $request->input('to_date'),
                        'd-m-Y'
                    );
                }
            );
    }

    public static function parseApiDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        foreach (['d-m-Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                // try next
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
