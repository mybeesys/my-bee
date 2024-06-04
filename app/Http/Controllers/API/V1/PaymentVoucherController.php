<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\AddPaymentVoucherPaymentRequest;
use App\Http\Requests\ListPaymentVoucherRequest;
use App\Http\Requests\StorePaymentVoucherRequest;
use App\Http\Resources\PaymentVoucherResource;
use App\Models\Acc4;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentVoucher;
use App\Models\PaymentVoucherPayment;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\FileAdder;


class PaymentVoucherController extends BaseController
{
    public function index(ListPaymentVoucherRequest $request)
    {
        $data = PaymentVoucher::with(['invoice', 'payments.media', 'payments.debitAccount', 'payments.creditAccount', 'user', 'acc4'])
            ->when($request->for, function (Builder $builder) use ($request) {
                return $builder->where('for', $request->for);
            })
            ->when($request->invoice_id, function (Builder $builder) use ($request) {
                return $builder->where('invoice_id', $request->invoice_id);
            })
            ->when($request->date, function (Builder $builder) use ($request) {
                return $builder->whereDate('date', $request->date);
            })
            ->when($request->from_date or $request->to_date, function (Builder $builder) use ($request) {
                return $builder->whereDateBetween('created_at', $request->from_date, $request->to_date, "d-m-Y");
            })
            ->when($request->sort, function (Builder $builder) use ($request) {
                if ($request->sort == 'oldest')
                    return $builder->oldest();
                return $builder->latest();
            })
            ->get();


        return $this->responder(__('messages.api.retrieved'), 200, PaymentVoucherResource::collection($data))
            ->respond();
    }

    public function store(StorePaymentVoucherRequest $request)
    {
        try {
            $data = $request->validated();

            $data['tenant_id'] = $this->getTenantId();
            $data['user_id'] = auth('sanctum')->id();
            $data['no'] = generate_payment_voucher();

            $invoice = Invoice::with('items')->find($data['invoice_id'] ?? null);

            if(!$invoice)
                unset($data['invoice_id']);

            DB::beginTransaction();


            if ($invoice and PaymentVoucher::where('invoice_id', $invoice->id)->get()->count() > 0) {
                return $this->errorBadRequest()->message(__("fields.voucher_already_exists_for_this_invoice"))->respond();
            }

            if ($invoice and collect($data['payments'])->sum('amount') > $invoice->getItemsCost(true, true, true)) {
                return $this->errorBadRequest()->message(__("fields.payments_are_bigger_than_invoice_amount"))->respond();
            }

            $paymentVoucher = PaymentVoucher::create(Arr::except($data, ['payments']));

            foreach ($data['payments'] as $index => $payment) {
                $payment = $paymentVoucher->payments()->create([
                    'tenant_id' => $this->getTenantId(),
                    'user_id' => $data['user_id'],
                    'payment_voucher_id' => $paymentVoucher->id,
                    'model_type' => $invoice ? get_class($invoice) : null,
                    'model_id' => $invoice?->id,
                    'credit_acc4_code' => $payment['acc4_code'],
                    'debit_acc4_code' => $data['acc4_code'],
                    'amount' => $payment['amount'],
                    'date' => $payment['date'],
                    'statement' => $payment['statement'],
                ]);


                if ($request->hasFile("payments.$index.attachments.$index")) {
                    $payment->addMultipleMediaFromRequest(["payments.$index.attachments"])
                        ->each(fn(FileAdder $fileAdder) => $fileAdder->toMediaCollection('attachments'));
                }


            }
            DB::commit();

            return $this->responder(__('messages.api.created'), 201, new PaymentVoucherResource($paymentVoucher))
                ->respond();

        } catch (\Throwable $exception) {
            DB::rollBack();
            return $this->error($exception)->respond();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = PaymentVoucher::with(['invoice', 'payments.media', 'payments.debitAccount', 'payments.creditAccount', 'user', 'acc4'])->findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new PaymentVoucherResource($item))->respond();
    }

    public function addPayment(AddPaymentVoucherPaymentRequest $request){
        $paymentVoucher = PaymentVoucher::with(['payments', 'invoice'])->findOrFail($request->payment_voucher_id);

        $data = $request->validated();

        if ($paymentVoucher->invoice and ($paymentVoucher->payments->sum('amount') + floatval($request->amount)) > $paymentVoucher->invoice->getItemsCost(true, true, true)) {
            return $this->errorBadRequest()->message(__("fields.payments_are_bigger_than_invoice_amount"))->respond();
        }

        PaymentVoucherPayment::create([
            'tenant_id' => $this->getTenantId(),
            'user_id' => auth('sanctum')->id(),
            'payment_voucher_id' => $paymentVoucher->id,
            'model_type' => $paymentVoucher->invoice ? get_class($paymentVoucher->invoice) : null,
            'model_id' => $paymentVoucher->invoice_id,
            'credit_acc4_code' => $paymentVoucher->acc4_code,
            'debit_acc4_code' => $data['acc4_code'],
            'amount' => $data['amount'],
            'date' => $data['date'],
            'statement' => $data['statement'],
        ]);

        if ($request->hasFile("attachments")) {
            $paymentVoucher->addMultipleMediaFromRequest(["attachments"])
                ->each(fn(FileAdder $fileAdder) => $fileAdder->toMediaCollection('attachments'));
        }

        $paymentVoucher->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new PaymentVoucherResource($paymentVoucher))->respond();

    }
    public function getSupplierInvoices($supplier_acc4_code)
    {
        $data = Invoice::dropdownUnpaidForSupplier(Supplier::whereRelation('acc4', 'code', $supplier_acc4_code)->firstOrFail()->id, false);
        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();
    }

    public function getOtherEntities()
    {
        $data = Acc4::asOptions(exclude_item_class: [Supplier::class, Customer::class, Product::class, ProductVariant::class, ProductExtra::class], with_code: true);
        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();
    }

    public function getInvoiceInfo($id)
    {
        $invoice = Invoice::with(['items', 'additionalCosts', 'services'])->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, [
            'totalInvoice' => format_amount($invoice->getItemsCost(true, true, true)),
            'totalPaidAmount' => format_amount($invoice->total_paid),
            'totalUnpaidAmount' => format_amount($invoice->total_unpaid),
        ])->respond();
    }

    public function getCreditAccounts()
    {
        $data = Acc4::whereIn('code', [120100001])->OrWhereIn('acc3_code', [1227])->pluck('name', 'code');
        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();
    }
}
