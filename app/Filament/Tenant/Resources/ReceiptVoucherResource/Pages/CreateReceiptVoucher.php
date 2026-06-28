<?php

namespace App\Filament\Tenant\Resources\ReceiptVoucherResource\Pages;

use App\Filament\Tenant\Resources\ReceiptVoucherResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ReceiptVoucher;
use App\Services\AccountingService;
use App\Services\InvoicePaymentTermsService;
use App\Services\ReceiptVoucherAllocationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use function Filament\Support\is_app_url;

class CreateReceiptVoucher extends CreateRecord
{
    protected static string $resource = ReceiptVoucherResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        parent::mount();

        $invoiceId = request()->integer('invoice_id');
        $orderId = request()->integer('order_id');

        $fill = [
            'no' => generate_receipt_voucher(),
            'user_id' => auth()->id(),
            'date' => now(),
            'for' => 'customer',
            'allocation_mode' => 'selected',
            'acc4_code' => null,
            'invoice_id' => null,
            'debit_acc4_code' => '120100001',
            'paid_amount' => null,
            'description' => null,
            'preselected_invoice_id' => null,
            'customer_invoices' => [],
        ];

        if ($orderId) {
            $order = Order::with(['customer.acc4', 'invoice'])->find($orderId);

            if ($order?->customer?->acc4) {
                $fill['acc4_code'] = $order->customer->acc4->code;
            }

            if ($order?->invoice_id) {
                $invoiceId = $order->invoice_id;
            }
        }

        if ($invoiceId) {
            $invoice = Invoice::with('customer.acc4')->find($invoiceId);

            if ($invoice) {
                $fill['acc4_code'] = $invoice->customer?->acc4?->code;
                $fill['preselected_invoice_id'] = $invoice->id;
                $fill['paid_amount'] = number_format(max(0, (float) $invoice->total_unpaid), currency_decimals(), '.', '');
            }
        }

        $this->form->fill($fill);

        if ($fill['acc4_code']) {
            ReceiptVoucherResource::refreshCustomerInvoiceLines($this);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['payments'], $data['customer_invoices'], $data['preselected_invoice_id'], $data['paid_amount'], $data['debit_acc4_code'], $data['description'], $data['allocation_mode']);

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
        if (($this->data['for'] ?? 'customer') !== 'customer') {
            $this->beforeCreateLegacy();

            return;
        }
    }

    protected function beforeCreateLegacy(): void
    {
        if ($this->data['invoice_id']) {
            $existingVoucher = ReceiptVoucher::findForInvoice((int) $this->data['invoice_id']);

            if ($existingVoucher) {
                Notification::make()
                    ->title(__('fields.voucher_already_exists_for_this_invoice'))
                    ->info()
                    ->send();

                $this->redirect(static::getResource()::getUrl('edit', ['record' => $existingVoucher]));
                $this->halt();
            }

            $invoice = Invoice::with(['salesPayments', 'items', 'additionalCosts', 'services'])
                ->findOrFail($this->data['invoice_id']);

            if ($this->getPaymentsAmount() > $invoice->getItemsCost(true, true, true)) {
                fns()->sendWarning(__('fields.payments_are_bigger_than_invoice_amount'));
                $this->halt();
            }
        }
    }

    public function create(bool $another = false): void
    {
        if (($this->data['for'] ?? 'customer') === 'customer') {
            $this->createCustomerAllocationVoucher($another);

            return;
        }

        $this->createLegacyVoucher($another);
    }

    protected function createCustomerAllocationVoucher(bool $another = false): void
    {
        $this->authorizeAccess();
        $this->callHook('beforeValidate');
        $this->form->getState();
        $this->callHook('afterValidate');

        try {
            DB::beginTransaction();

            $acc4Code = (int) ($this->data['acc4_code'] ?? 0);
            $debitAcc4Code = (string) ($this->data['debit_acc4_code'] ?? '120100001');
            $paidAmount = ReceiptVoucherResource::normalizeReceiptPaidAmount($this->data['paid_amount'] ?? 0);
            $mode = $this->data['allocation_mode'] ?? 'fifo';
            $description = trim((string) ($this->data['description'] ?? ''));
            $date = $this->data['date'] ?? now();

            $invoices = ReceiptVoucherAllocationService::instance()
                ->unpaidSalesInvoicesForAcc4Code($acc4Code);

            $selectedIds = collect($this->data['customer_invoices'] ?? [])
                ->filter(fn ($line) => ! empty($line['selected']))
                ->pluck('invoice_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($mode === 'selected' && $selectedIds === [] && filled($this->data['preselected_invoice_id'] ?? null)) {
                $selectedIds = [(int) $this->data['preselected_invoice_id']];
            }

            $allocations = ReceiptVoucherAllocationService::instance()->allocate(
                $paidAmount,
                $invoices,
                $mode,
                $selectedIds,
            );

            $this->record = InvoicePaymentTermsService::instance()->recordAllocatedCustomerReceipt(
                (string) $acc4Code,
                $debitAcc4Code,
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

            if ($this->record->invoice
                && $this->record->invoice->total_unpaid == 0
                && $this->record->invoice->order
                && $this->record->invoice->order->paid_date === null) {
                $this->record->invoice->order->update(['paid_date' => now()]);
            }

            foreach ($this->record->payments as $payment) {
                if (! $payment->transaction_completed) {
                    $op = $payment->debitAccount?->acc3_code == 1227
                        ? make_bank_transfer_receipt_voucher_op()
                        : make_cash_receipt_voucher_op();

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
}
