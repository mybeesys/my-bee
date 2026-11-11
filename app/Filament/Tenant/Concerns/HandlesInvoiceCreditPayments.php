<?php

namespace App\Filament\Tenant\Concerns;

use App\Services\InvoicePaymentTermsService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait HandlesInvoiceCreditPayments
{
    protected ?array $pendingCreditPaymentUi = null;

    protected static function stripCreditPaymentUiFields(array $data): array
    {
        foreach ([
            'credit_payment_account',
            'credit_payment_amount',
            'credit_payment_date',
            'credit_payment_statement',
            'credit_payment_ui',
        ] as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    protected function prepareInvoiceFormDataForPersistence(array $data): array
    {
        $this->assertRequiredInvoiceFields($data);
        $this->stashPendingCreditPaymentUi();

        return static::stripCreditPaymentUiFields($data);
    }

    protected function onValidationError(ValidationException $exception): void
    {
        $this->notifyMissingRequiredInvoiceFields($exception);
    }

    protected function assertRequiredInvoiceFields(array $data): void
    {
        $errors = [];

        if (! in_array($data['payment_terms'] ?? null, ['cash', 'credit'], true)) {
            $errors['payment_terms'] = __('fields.payment_terms_required');
        }

        if ($errors === []) {
            return;
        }

        throw ValidationException::withMessages($errors);
    }

    protected function notifyMissingRequiredInvoiceFields(ValidationException $exception): void
    {
        $labels = [
            'payment_terms' => __('fields.payment_terms'),
            'customer_id' => __('fields.client'),
            'supplier_id' => __('fields.supplier'),
            'date' => __('fields.date'),
            'warehouse_id' => __('fields.warehouse'),
            'items' => __('fields.items'),
            'no' => __('fields.invoice_no'),
        ];

        $names = [];

        foreach ($exception->errors() as $field => $messages) {
            $root = explode('.', str_replace('data.', '', (string) $field))[0];
            $names[] = $labels[$root] ?? (is_array($messages) ? ($messages[0] ?? $root) : $root);
        }

        $names = array_values(array_unique(array_filter($names)));
        $separator = app()->getLocale() === 'ar' ? '، ' : ', ';

        Notification::make()
            ->warning()
            ->title(__('fields.required_fields_missing_title'))
            ->body($names === []
                ? __('fields.required_fields_missing_generic')
                : __('fields.required_fields_missing_body', ['fields' => implode($separator, $names)]))
            ->persistent()
            ->send();
    }

    protected function stashPendingCreditPaymentUi(): void
    {
        $ui = $this->creditPaymentUiDataFromFormState();
        $amount = $this->normalizeCreditPaymentAmount($ui['credit_payment_amount'] ?? 0);

        if ($amount <= 0) {
            $this->pendingCreditPaymentUi = null;

            return;
        }

        $this->pendingCreditPaymentUi = [
            'credit_payment_amount' => $amount,
            'credit_payment_account' => (string) ($ui['credit_payment_account'] ?? '120100001'),
            'credit_payment_date' => $ui['credit_payment_date'] ?? now()->format('Y-m-d'),
            'credit_payment_statement' => trim((string) ($ui['credit_payment_statement'] ?? '')),
        ];
    }

    public function registerCreditPayment(): void
    {
        $this->attemptPendingCreditPayment();
    }

    public function processPendingCreditPayment(): void
    {
        $this->attemptPendingCreditPayment();
    }

    protected function attemptPendingCreditPayment(): void
    {
        $invoice = $this->record;

        if (! $invoice?->exists || ! InvoicePaymentTermsService::instance()->isCredit($invoice)) {
            return;
        }

        $ui = $this->creditPaymentUiData();
        $amount = $this->normalizeCreditPaymentAmount($ui['credit_payment_amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        $account = (string) ($ui['credit_payment_account'] ?? '120100001');
        $date = $ui['credit_payment_date'] ?? now()->toDateString();
        $statement = trim((string) ($ui['credit_payment_statement'] ?? ''));

        if ($account === '') {
            $account = '120100001';
        }

        if ($statement === '') {
            $statement = __('fields.invoice_partial_payment_statement', ['no' => $invoice->no]);
        }

        try {
            DB::beginTransaction();

            InvoicePaymentTermsService::instance()->recordCreditPayment($invoice, [
                'amount' => $amount,
                'account_code' => $account,
                'date' => $date,
                'statement' => $statement,
            ]);

            DB::commit();
        } catch (ValidationException $exception) {
            DB::rollBack();

            foreach ($exception->errors() as $messages) {
                fns()->sendWarning($messages[0] ?? __('fields.invoice_payment_failed'));
            }

            return;
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            Notification::make()
                ->title(__('fields.invoice_payment_failed'))
                ->danger()
                ->send();

            return;
        }

        $this->pendingCreditPaymentUi = null;
        $this->resetCreditPaymentFields();
        $this->refreshInvoicePayments();

        fns()->saved();
    }

    protected function creditPaymentUiDataFromFormState(): array
    {
        if (isset($this->data['credit_payment_ui']) && is_array($this->data['credit_payment_ui'])) {
            return $this->data['credit_payment_ui'];
        }

        return [
            'credit_payment_account' => $this->data['credit_payment_account'] ?? null,
            'credit_payment_amount' => $this->data['credit_payment_amount'] ?? null,
            'credit_payment_date' => $this->data['credit_payment_date'] ?? null,
            'credit_payment_statement' => $this->data['credit_payment_statement'] ?? null,
        ];
    }

    protected function creditPaymentUiData(): array
    {
        if (filled($this->pendingCreditPaymentUi)) {
            return $this->pendingCreditPaymentUi;
        }

        return $this->creditPaymentUiDataFromFormState();
    }

    protected function normalizeCreditPaymentAmount(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return 0;
        }

        $normalized = preg_replace('/[^\d.]/', '', $value);

        return (float) $normalized;
    }

    protected function resetCreditPaymentFields(): void
    {
        if (isset($this->data['credit_payment_ui']) && is_array($this->data['credit_payment_ui'])) {
            $this->data['credit_payment_ui']['credit_payment_amount'] = 0;
            $this->data['credit_payment_ui']['credit_payment_statement'] = null;
            $this->data['credit_payment_ui']['credit_payment_date'] = now()->format('Y-m-d');

            return;
        }

        $this->data['credit_payment_amount'] = 0;
        $this->data['credit_payment_statement'] = null;
        $this->data['credit_payment_date'] = now()->format('Y-m-d');
    }

    protected function refreshInvoicePayments(): void
    {
        $this->record->refresh();
        $this->record->load(
            $this->record->type === 'purchases' ? ['purchasePayments'] : ['salesPayments']
        );
    }
}
