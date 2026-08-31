<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\AddReceiptVoucherPaymentRequest;
use App\Http\Requests\ListReceiptVoucherRequest;
use App\Http\Requests\PreviewReceiptVoucherAllocationRequest;
use App\Http\Requests\StoreReceiptVoucherRequest;
use App\Http\Resources\ReceiptVoucherResource;
use App\Models\Acc4;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ReceiptVoucher;
use App\Services\ReceiptVoucherService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ReceiptVoucherController extends BaseController
{
    public function __construct(
        protected ReceiptVoucherService $receiptVouchers,
    ) {
    }

    public function index(ListReceiptVoucherRequest $request)
    {
        $sort = $request->input('sort', 'latest');

        $query = ReceiptVoucher::query()
            ->with(ReceiptVoucherService::eagerLoads())
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');

                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('no', 'like', "%{$search}%")
                        ->orWhereHas('acc4', fn (Builder $acc4) => $acc4->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('invoice', fn (Builder $invoice) => $invoice->where('no', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('for') && ! is_array($request->input('for')), fn (Builder $builder) => $builder->where('for', $request->input('for')))
            ->when(is_array($request->input('for')), fn (Builder $builder) => $builder->whereIn('for', $request->input('for')))
            ->when($request->invoice_id, fn (Builder $builder) => $builder->where('invoice_id', $request->invoice_id))
            ->when($request->filled('invoice_ids'), fn (Builder $builder) => $builder->whereIn('invoice_id', $request->input('invoice_ids')))
            ->when($request->acc4_code, fn (Builder $builder) => $builder->where('acc4_code', $request->acc4_code))
            ->when($request->filled('acc4_codes'), fn (Builder $builder) => $builder->whereIn('acc4_code', $request->input('acc4_codes')))
            ->when($request->date, fn (Builder $builder) => $builder->whereDate('date', Carbon::parse($request->date)->format('Y-m-d')))
            ->when($request->created_from, fn (Builder $builder) => $builder->whereDate('created_at', '>=', Carbon::parse($request->created_from)->format('Y-m-d')))
            ->when($request->created_until, fn (Builder $builder) => $builder->whereDate('created_at', '<=', Carbon::parse($request->created_until)->format('Y-m-d')))
            ->when($request->from_date || $request->to_date, fn (Builder $builder) => $builder->whereDateBetween('created_at', $request->from_date, $request->to_date, 'd-m-Y'))
            ->when($sort === 'oldest', fn (Builder $builder) => $builder->orderBy('created_at'))
            ->when($sort !== 'oldest', fn (Builder $builder) => $builder->orderByDesc('created_at'));

        $data = $query->get();
        $payload = collect(ReceiptVoucherResource::collection($data)->resolve());
        $additionalFilters = [];

        if ($request->boolean('include_summaries', true)) {
            $additionalFilters['listSummaries'] = $this->receiptVouchers->listSummaries($data);
        }

        if ($request->boolean('paginate')) {
            return $this->responder(__('messages.api.retrieved'), 200, [], [], $additionalFilters)->paginate($payload);
        }

        return $this->responder(__('messages.api.retrieved'), 200, $payload, [], $additionalFilters)->respond();
    }

    public function store(StoreReceiptVoucherRequest $request)
    {
        $data = $request->validated();

        foreach ($data['payments'] ?? [] as $index => $payment) {
            if ($request->hasFile("payments.$index.attachments")) {
                $data['payments'][$index]['attachments'] = $request->file("payments.$index.attachments");
            }
        }

        try {
            $voucher = $this->receiptVouchers->create(
                $data,
                (int) $this->getTenantId(),
                (int) auth('sanctum')->id(),
            );

            return $this->responder(__('messages.api.created'), 201, new ReceiptVoucherResource($voucher))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function show(string $id)
    {
        $item = ReceiptVoucher::with(ReceiptVoucherService::eagerLoads())->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, new ReceiptVoucherResource($item))->respond();
    }

    public function addPayment(AddReceiptVoucherPaymentRequest $request)
    {
        $receiptVoucher = ReceiptVoucher::findOrFail($request->receipt_voucher_id);
        $data = $request->validated();
        $attachments = $request->hasFile('attachments') ? $request->file('attachments') : null;

        try {
            $voucher = $this->receiptVouchers->addPayment(
                $receiptVoucher,
                $data,
                (int) $this->getTenantId(),
                (int) auth('sanctum')->id(),
                is_array($attachments) ? $attachments : ($attachments ? [$attachments] : null),
            );

            return $this->responder(__('messages.api.updated'), 200, new ReceiptVoucherResource($voucher))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function allocationPreview(PreviewReceiptVoucherAllocationRequest $request)
    {
        $data = $request->validated();
        $selectedIds = $data['selected_invoice_ids'] ?? [];

        if (($data['allocation_mode'] ?? 'fifo') === 'selected'
            && $selectedIds === []
            && filled($data['preselected_invoice_id'] ?? null)) {
            $selectedIds = [(int) $data['preselected_invoice_id']];
        }

        $lines = $this->receiptVouchers->allocationPreview(
            (string) $data['acc4_code'],
            $data['allocation_mode'] ?? 'fifo',
            $selectedIds,
            (float) ($data['paid_amount'] ?? 0),
        );

        return $this->responder(__('messages.api.retrieved'), 200, [
            'customerInvoices' => $lines,
        ])->respond();
    }

    public function prefill()
    {
        $payload = $this->receiptVouchers->prefill(
            request()->integer('invoice_id') ?: null,
            request()->integer('order_id') ?: null,
        );

        return $this->responder(__('messages.api.retrieved'), 200, $payload)->respond();
    }

    public function getCustomerInvoices($customer_acc4_code)
    {
        $customer = Customer::query()->whereRelation('acc4', 'code', $customer_acc4_code)->firstOrFail();

        return $this->responder(__('messages.api.retrieved'), 200, [
            'invoices' => Invoice::dropdownUnpaidForCustomer($customer->id, false),
            'lines' => $this->receiptVouchers->allocationPreview((string) $customer_acc4_code, 'fifo', [], 0),
        ])->respond();
    }

    public function getOtherEntities()
    {
        return $this->responder(__('messages.api.retrieved'), 200, Acc4::userCreatedOtherPartyAccountOptions())->respond();
    }

    public function getInvoiceInfo($id)
    {
        $invoice = Invoice::with(['items', 'additionalCosts', 'services'])->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, [
            'totalInvoice' => format_amount($invoice->getItemsCost(true, true, true)),
            'totalPaidAmount' => format_amount($invoice->total_paid),
            'totalUnpaidAmount' => format_amount($invoice->total_unpaid),
            'totalInvoiceNumeric' => round((float) $invoice->getItemsCost(true, true, true), currency_decimals()),
            'totalPaidAmountNumeric' => round((float) $invoice->total_paid, currency_decimals()),
            'totalUnpaidAmountNumeric' => round((float) $invoice->total_unpaid, currency_decimals()),
        ])->respond();
    }

    public function getCreditAccounts()
    {
        return $this->responder(__('messages.api.retrieved'), 200, Acc4::collectionAccountOptions())->respond();
    }

    public function getVoucherPaymentAccounts()
    {
        return $this->responder(__('messages.api.retrieved'), 200, Acc4::voucherOtherEntityPaymentAccountOptions())->respond();
    }
}
