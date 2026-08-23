<?php

namespace App\Services;

use App\Models\Acc4;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\TaxProfile;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public const EXPENSE_ACCOUNT_CODE = '122300001';

    public const EXPENSE_TAX_ACCOUNT_CODE = '122800001';

    public static function instance(): self
    {
        return new self();
    }

    /**
     * @return array<int, string>
     */
    public static function eagerLoads(): array
    {
        return [
            'category',
            'taxProfile.taxes',
            'debitAccount',
            'creditAccount',
            'media',
        ];
    }

    public function parseDate(mixed $value): Carbon
    {
        return Carbon::parse($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function prefill(): array
    {
        return [
            'creditAcc4Code' => Acc4::defaultCollectionAccountCode(),
            'debitAcc4Code' => self::EXPENSE_ACCOUNT_CODE,
            'date' => now()->format('Y-m-d'),
            'amountIncludesTax' => false,
            'taxProfileId' => null,
            'expenseCategoryId' => null,
            'amount' => null,
            'description' => null,
        ];
    }

    /**
     * Live tax preview for mobile create form (matches Filament afterStateUpdated).
     *
     * @return array<string, mixed>
     */
    public function taxPreview(float $amount, ?int $taxProfileId, bool $amountIncludesTax): array
    {
        if (! $amountIncludesTax || ! $taxProfileId || $amount <= 0) {
            return [
                'amount' => round($amount, currency_decimals()),
                'amountWithoutTax' => round($amount, currency_decimals()),
                'tax' => 0,
                'total' => round($amount, currency_decimals()),
                'taxPercent' => 0,
                'taxProfile' => null,
            ];
        }

        $taxProfile = TaxProfile::with('taxes')->findOrFail($taxProfileId);
        $tax = round(
            MathService::instance()->getTaxFromTaxProfile($amount, $taxProfile, true),
            currency_decimals()
        );
        $amountWithoutTax = round($amount - $tax, currency_decimals());

        return [
            'amount' => round($amount, currency_decimals()),
            'amountWithoutTax' => $amountWithoutTax,
            'tax' => $tax,
            'total' => round($amount, currency_decimals()),
            'taxPercent' => round((float) $taxProfile->taxes->sum('percent'), currency_decimals()),
            'taxProfile' => $taxProfile->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>|null  $attachments
     */
    public function create(array $data, int $tenantId, ?array $attachments = null): Expense
    {
        $client = \App\Models\Tenant::query()->find($tenantId)?->client;

        if ($client && subscription_resource_maxed_out('expenses', $client)) {
            throw ValidationException::withMessages([
                'expense' => subscription_limit_exceeded_message('expenses', $client) ?? __('messages.api.permission_denied'),
            ]);
        }

        return DB::transaction(function () use ($data, $tenantId, $attachments) {
            $payload = $this->prepareCreatePayload($data, $tenantId);

            $expense = Expense::create($payload);

            if ($attachments) {
                foreach ($attachments as $file) {
                    if ($file instanceof UploadedFile) {
                        $expense->addMedia($file)->toMediaCollection('attachments');
                    }
                }
            }

            $this->postAccounting($expense);

            return $expense->fresh()->load(self::eagerLoads());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>|null  $attachments
     */
    public function update(Expense $expense, array $data, ?array $attachments = null): Expense
    {
        return DB::transaction(function () use ($expense, $data, $attachments) {
            $updates = [];

            if (array_key_exists('description', $data)) {
                $updates['description'] = $data['description'];
            }

            if (array_key_exists('expense_category_id', $data)) {
                $updates['expense_category_id'] = $data['expense_category_id'];
            }

            if (array_key_exists('date', $data) && filled($data['date'])) {
                $updates['date'] = $this->parseDate($data['date']);
            }

            if ($updates !== []) {
                $expense->update($updates);
            }

            if ($attachments) {
                foreach ($attachments as $file) {
                    if ($file instanceof UploadedFile) {
                        $expense->addMedia($file)->toMediaCollection('attachments');
                    }
                }
            }

            return $expense->fresh()->load(self::eagerLoads());
        });
    }

    /**
     * Same double-entry as Filament ExpenseResource::postExpenseCreated.
     */
    public function postAccounting(Expense $record): void
    {
        $op = make_taxes_op();
        $accService = new AccountingService();
        $accService
            ->setUp(
                $op->id,
                now(),
                main_currency_iso_code(),
                generate_double_entry_transaction_id(),
                $record->total,
                null,
                $record->description,
                $record->description,
                null,
                meta: ['type' => 'expense', 'id' => $record->id],
            )->make($record->credit_acc4_code, self::EXPENSE_ACCOUNT_CODE)
            ->finish();

        if ($record->tax > 0) {
            $op = make_taxes_op();
            $accService = new AccountingService();
            $accService
                ->setUp(
                    $op->id,
                    now(),
                    main_currency_iso_code(),
                    generate_double_entry_transaction_id(),
                    $record->tax,
                    null,
                    'Vat',
                    'Vat',
                    null,
                    meta: ['type' => 'expense', 'id' => $record->id],
                )->make(self::EXPENSE_TAX_ACCOUNT_CODE, self::EXPENSE_ACCOUNT_CODE)
                ->finish();
        }
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     * @return array<string, mixed>
     */
    public function listSummaries(Collection $expenses): array
    {
        $subTotal = round((float) $expenses->sum(fn (Expense $e) => (float) $e->sub_total), currency_decimals());
        $tax = round((float) $expenses->sum(fn (Expense $e) => (float) $e->tax), currency_decimals());
        $total = round((float) $expenses->sum(fn (Expense $e) => (float) $e->total), currency_decimals());

        return [
            'subTotal' => $subTotal,
            'tax' => $tax,
            'total' => $total,
            'currency' => main_currency_iso_code(),
            'count' => $expenses->count(),
        ];
    }

    /**
     * Overview cards (matches ExpensesOverview widget).
     *
     * @return array<int, array<string, mixed>>
     */
    public function overview(): array
    {
        $categories = ExpenseCategory::with(['expenses'])
            ->whereHas('expenses')
            ->get()
            ->sortBy(fn ($item) => $item->expenses->sum('sub_total'), SORT_REGULAR, false);

        $cards = [];

        foreach ($categories as $category) {
            $amount = (float) $category->expenses->sum('sub_total');
            $cards[] = [
                'id' => $category->id,
                'name' => $category->name,
                'total' => round($amount, currency_decimals()),
                'totalFormatted' => main_currency_iso_code().' '.format_amount($amount),
                'totalWritten' => numbers_to_words($amount),
                'expensesCount' => $category->expenses->count(),
            ];
        }

        $totalAmount = (float) $categories->pluck('expenses')->flatten()->sum('sub_total');

        $cards[] = [
            'id' => null,
            'name' => __('fields.total'),
            'total' => round($totalAmount, currency_decimals()),
            'totalFormatted' => main_currency_iso_code().' '.format_amount($totalAmount),
            'totalWritten' => numbers_to_words($totalAmount),
            'expensesCount' => Expense::count(),
            'isGrandTotal' => true,
        ];

        return array_values($cards);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareCreatePayload(array $data, int $tenantId): array
    {
        $amountIncludesTax = filter_var($data['amount_includes_tax'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filled($data['tax_profile_id'] ?? null);

        $payload = [
            'tenant_id' => $tenantId,
            'debit_acc4_code' => self::EXPENSE_ACCOUNT_CODE,
            'credit_acc4_code' => (string) $data['credit_acc4_code'],
            'expense_category_id' => (int) $data['expense_category_id'],
            'date' => $this->parseDate($data['date']),
            'description' => $data['description'],
            'amount' => round((float) $data['amount'], currency_decimals()),
            'tax' => 0,
            'tax_profile_id' => null,
            'tax_profile_data' => null,
        ];

        if ($amountIncludesTax && filled($data['tax_profile_id'] ?? null)) {
            $taxProfile = TaxProfile::with('taxes')->findOrFail($data['tax_profile_id']);
            $tax = round(
                MathService::instance()->getTaxFromTaxProfile($payload['amount'], $taxProfile, true),
                currency_decimals()
            );

            $payload['tax'] = $tax;
            $payload['tax_profile_id'] = $taxProfile->id;
            $payload['tax_profile_data'] = $taxProfile->toArray();
        }

        return $payload;
    }
}
