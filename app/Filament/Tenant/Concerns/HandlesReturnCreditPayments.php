<?php

namespace App\Filament\Tenant\Concerns;

use App\Services\ReturnPaymentTermsService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait HandlesReturnCreditPayments
{
    protected ?array $pendingCreditPaymentUi = null;

    protected static function stripReturnCreditPaymentUiFields(array $data): array
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

    protected function prepareReturnFormDataForPersistence(array $data): array
    {
        $this->stashPendingReturnCreditPaymentUi();

        if (($data['payment_terms'] ?? 'cash') === 'cash') {
            $data['refund_acc4_code'] = '120100001';
        } else {
            $data['refund_acc4_code'] = null;
        }

        return static::stripReturnCreditPaymentUiFields($data);
    }

    protected function stashPendingReturnCreditPaymentUi(): void
    {
        $ui = $this->returnCreditPaymentUiDataFromFormState();
        $amount = $this->normalizeReturnCreditPaymentAmount($ui['credit_payment_amount'] ?? 0);

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

    protected function returnCreditPaymentUiDataFromFormState(): array
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

    protected function returnCreditPaymentUiData(): array
    {
        if (filled($this->pendingCreditPaymentUi)) {
            return $this->pendingCreditPaymentUi;
        }

        return $this->returnCreditPaymentUiDataFromFormState();
    }

    protected function normalizeReturnCreditPaymentAmount(mixed $value): float
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

    protected function processPendingReturnCreditRefund(string $party, float $returnTotal): void
    {
        $paymentTerms = (string) ($this->data['payment_terms'] ?? 'cash');

        if ($paymentTerms === 'cash') {
            return;
        }

        $ui = $this->returnCreditPaymentUiData();
        $amount = $this->normalizeReturnCreditPaymentAmount($ui['credit_payment_amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        if ($amount > $returnTotal) {
            fns()->sendWarning(__('fields.payments_are_bigger_than_invoice_amount'));

            return;
        }

        $account = (string) ($ui['credit_payment_account'] ?? '120100001');
        $date = $ui['credit_payment_date'] ?? now()->toDateString();
        $statement = trim((string) ($ui['credit_payment_statement'] ?? ''));

        if ($account === '') {
            $account = '120100001';
        }

        try {
            DB::beginTransaction();

            if ($party === 'sales') {
                ReturnPaymentTermsService::instance()->recordSalesReturnCreditRefund($this->record, [
                    'amount' => $amount,
                    'account_code' => $account,
                    'date' => $date,
                    'statement' => $statement,
                ]);
            } else {
                ReturnPaymentTermsService::instance()->recordPurchaseReturnCreditRefund($this->record, [
                    'amount' => $amount,
                    'account_code' => $account,
                    'date' => $date,
                    'statement' => $statement,
                ]);
            }

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
        }
    }
}
