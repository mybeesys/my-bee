<?php

namespace App\Filament\Tenant\Resources\PaymentVoucherResource\Pages;

use App\Filament\Tenant\Resources\PaymentVoucherResource;
use App\Models\Acc4;
use App\Models\Invoice;
use App\Services\AccountingService;
use App\Services\InvoicePaymentTermsService;
use App\Services\PaymentVoucherAllocationService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use function Filament\Support\is_app_url;

class CreatePaymentVoucher extends CreateRecord
{
    protected static string $resource = PaymentVoucherResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        parent::mount();

        $invoiceId = request()->integer('invoice_id');

        $fill = [
            'no' => generate_payment_voucher(),
            'user_id' => auth()->id(),
            'date' => now(),
            'for' => 'supplier',
            'allocation_mode' => 'selected',
            'acc4_code' => null,
            'invoice_id' => null,
            'credit_acc4_code' => Acc4::defaultCollectionAccountCode(),
            'paid_amount' => null,
            'description' => null,
            'preselected_invoice_id' => null,
            'supplier_invoices' => [],
        ];

        if ($invoiceId) {
            $invoice = Invoice::with('supplier.acc4')->find($invoiceId);

            if ($invoice) {
                $fill['acc4_code'] = $invoice->supplier?->acc4?->code;
                $fill['preselected_invoice_id'] = $invoice->id;
                $fill['paid_amount'] = number_format(max(0, (float) $invoice->total_unpaid), currency_decimals(), '.', '');
            }
        }

        $this->form->fill($fill);

        if ($fill['acc4_code']) {
            PaymentVoucherResource::refreshSupplierInvoiceLines($this);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset(
            $data['payments'],
            $data['supplier_invoices'],
            $data['preselected_invoice_id'],
            $data['paid_amount'],
            $data['credit_acc4_code'],
            $data['description'],
            $data['allocation_mode'],
        );

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function getPaymentsAmount(): float
    {
        $paymentsAmount = 0;

        foreach ($this->data['payments'] ?? [] as $item) {
            $paymentsAmount += (float) ($item['amount'] ?? 0);
        }

        return $paymentsAmount;
    }

    protected function beforeCreate(): void
    {
        if (($this->data['for'] ?? 'supplier') !== 'supplier') {
            $this->beforeCreateLegacy();
        }
    }

    protected function beforeCreateLegacy(): void
    {
        if ($this->data['invoice_id']) {
            $invoice = Invoice::with(['purchasePayments'])->findOrFail($this->data['invoice_id']);

            if ($this->getPaymentsAmount() > $invoice->getItemsCost(true, true, true)) {
                fns()->sendWarning(__('fields.payments_are_bigger_than_invoice_amount'));
                $this->halt();
            }
        }
    }

    public function create(bool $another = false): void
    {
        if (($this->data['for'] ?? 'supplier') === 'supplier') {
            $this->createSupplierAllocationVoucher($another);

            return;
        }

        $this->createLegacyVoucher($another);
    }

    protected function createSupplierAllocationVoucher(bool $another = false): void
    {
        $this->authorizeAccess();
        $this->callHook('beforeValidate');
        $this->form->getState();
        $this->callHook('afterValidate');

        try {
            DB::beginTransaction();

            $creditAcc4Code = (string) ($this->data['credit_acc4_code'] ?? '120100001');
            $acc4Code = (int) ($this->data['acc4_code'] ?? 0);
            $paidAmount = PaymentVoucherResource::normalizePaymentPaidAmount($this->data['paid_amount'] ?? 0);
            $mode = $this->data['allocation_mode'] ?? 'fifo';
            $description = trim((string) ($this->data['description'] ?? ''));
            $date = $this->data['date'] ?? now();

            $invoices = PaymentVoucherAllocationService::instance()
                ->unpaidPurchaseInvoicesForAcc4Code($acc4Code);

            $selectedIds = collect($this->data['supplier_invoices'] ?? [])
                ->filter(fn ($line) => ! empty($line['selected']))
                ->pluck('invoice_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($mode === 'selected' && $selectedIds === [] && filled($this->data['preselected_invoice_id'] ?? null)) {
                $selectedIds = [(int) $this->data['preselected_invoice_id']];
            }

            $allocations = PaymentVoucherAllocationService::instance()->allocate(
                $paidAmount,
                $invoices,
                $mode,
                $selectedIds,
            );

            $this->record = InvoicePaymentTermsService::instance()->recordAllocatedSupplierPayment(
                $creditAcc4Code,
                $date,
                $description,
                $allocations,
            );

            $this->callHook('afterCreate');

            DB::commit();
        } catch (Halt) {
            DB::rollBack();

            return;
        } catch (ValidationException $exception) {
            DB::rollBack();

            throw $exception;
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);
            fns()->sendDanger('خطأ', 'فشلت العملية الرجاء التواصل مع الدعم الفني');
            $this->halt();

            return;
        }

        $this->getCreatedNotification()?->send();

        if ($another) {
            $this->form->model($this->getRecord()::class);
            $this->record = null;
            $this->fillForm();

            return;
        }

        $redirectUrl = $this->getRedirectUrl();

        if (FilamentView::hasSpaMode()) {
            $this->redirect($redirectUrl, navigate: is_app_url($redirectUrl));
        } else {
            $this->redirect($redirectUrl);
        }
    }

    protected function createLegacyVoucher(bool $another = false): void
    {
        $this->authorizeAccess();
        $this->callHook('beforeValidate');

        $data = $this->form->getState();

        $this->callHook('afterValidate');

        try {
            DB::beginTransaction();

            $data = $this->mutateFormDataBeforeCreate($data);

            $this->callHook('beforeCreate');

            $this->record = $this->handleRecordCreation($data);

            $this->form->model($this->getRecord())->saveRelationships();

            $this->record->refresh();

            foreach ($this->record->payments as $payment) {
                if (! $payment->transaction_completed) {
                    $op = $payment->creditAccount?->acc3_code == 1227
                        ? make_bank_transfer_payment_voucher_op()
                        : make_cash_payment_voucher_op();

                    (new AccountingService())
                        ->setUp(
                            $op->id,
                            now(),
                            main_currency_iso_code(),
                            generate_double_entry_transaction_id(),
                            $payment->amount,
                            null,
                            $payment->statement,
                            $payment->statement,
                            $this->record->invoice_id,
                        )->make($payment->credit_acc4_code, $payment->debit_acc4_code)
                        ->finish();

                    $payment->update(['transaction_completed' => 1]);
                }
            }

            DB::commit();

            $this->callHook('afterCreate');
        } catch (Halt) {
            DB::rollBack();

            return;
        } catch (ValidationException $exception) {
            DB::rollBack();

            return;
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);
            fns()->sendDanger('خطأ', 'فشلت العملية الرجاء التواصل مع الدعم الفني');
            $this->halt();

            return;
        }

        $this->getCreatedNotification()?->send();

        if ($another) {
            $this->form->model($this->getRecord()::class);
            $this->record = null;
            $this->fillForm();

            return;
        }

        $redirectUrl = $this->getRedirectUrl();

        if (FilamentView::hasSpaMode()) {
            $this->redirect($redirectUrl, navigate: is_app_url($redirectUrl));
        } else {
            $this->redirect($redirectUrl);
        }
    }
}
