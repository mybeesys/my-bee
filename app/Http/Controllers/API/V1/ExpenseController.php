<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListExpensesRequest;
use App\Http\Requests\PreviewExpenseTaxRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\Acc4Resource;
use App\Http\Resources\ExpenseResource;
use App\Models\Acc4;
use App\Models\Expense;
use App\Services\ExpenseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ExpenseController extends BaseController
{
    public function __construct(
        protected ExpenseService $expenses,
    ) {
    }

    public function index(ListExpensesRequest $request)
    {
        $sort = $request->input('sort', 'latest');

        $query = Expense::query()
            ->with(ExpenseService::eagerLoads())
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');

                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('description', 'like', "%{$search}%")
                        ->orWhereHas('creditAccount', fn (Builder $acc) => $acc->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('category', fn (Builder $cat) => $cat->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('debit_acc4_code'), fn (Builder $builder) => $builder->whereIn('debit_acc4_code', Arr::wrap($request->debit_acc4_code)))
            ->when($request->filled('credit_acc4_code'), fn (Builder $builder) => $builder->whereIn('credit_acc4_code', Arr::wrap($request->credit_acc4_code)))
            ->when($request->filled('credit_acc4_codes'), fn (Builder $builder) => $builder->whereIn('credit_acc4_code', Arr::wrap($request->credit_acc4_codes)))
            ->when($request->filled('expense_category_id') && ! is_array($request->expense_category_id), fn (Builder $builder) => $builder->where('expense_category_id', $request->expense_category_id))
            ->when(is_array($request->input('expense_category_id')), fn (Builder $builder) => $builder->whereIn('expense_category_id', $request->input('expense_category_id')))
            ->when($request->filled('expense_category_ids'), fn (Builder $builder) => $builder->whereIn('expense_category_id', Arr::wrap($request->expense_category_ids)))
            ->when($request->date_from, fn (Builder $builder) => $builder->whereDate('date', '>=', Carbon::parse($request->date_from)->format('Y-m-d')))
            ->when($request->date_until, fn (Builder $builder) => $builder->whereDate('date', '<=', Carbon::parse($request->date_until)->format('Y-m-d')))
            ->when($request->from_date || $request->to_date, fn (Builder $builder) => $builder->whereDateBetween('date', $request->from_date, $request->to_date, 'd-m-Y'))
            ->when($request->min_amount || $request->max_amount, fn (Builder $builder) => $builder->whereBetween('amount', [$request->min_amount ?? 0, $request->max_amount ?? PHP_INT_MAX]))
            ->when($request->boolean('attachments'), fn (Builder $builder) => $builder->whereHas('media'))
            ->when($sort === 'oldest', fn (Builder $builder) => $builder->orderBy('created_at'))
            ->when($sort !== 'oldest', fn (Builder $builder) => $builder->orderByDesc('created_at'));

        $data = $query->get();
        $payload = collect(ExpenseResource::collection($data)->resolve());
        $additionalFilters = [];

        if ($request->boolean('include_summaries', true)) {
            $additionalFilters['listSummaries'] = $this->expenses->listSummaries($data);
        }

        if ($request->boolean('paginate')) {
            return $this->responder(__('messages.api.retrieved'), 200, [], [], $additionalFilters)->paginate($payload);
        }

        return $this->responder(__('messages.api.retrieved'), 200, $payload, [], $additionalFilters)->respond();
    }

    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();
        $attachments = $request->hasFile('attachments') ? $request->file('attachments') : null;

        try {
            $expense = $this->expenses->create(
                $data,
                (int) $this->getTenantId(),
                is_array($attachments) ? $attachments : ($attachments ? [$attachments] : null),
            );

            return $this->responder(__('messages.api.created'), 201, new ExpenseResource($expense))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function show(string $id)
    {
        $item = Expense::with(ExpenseService::eagerLoads())->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, new ExpenseResource($item))->respond();
    }

    public function update(UpdateExpenseRequest $request, string $id)
    {
        $item = Expense::with(ExpenseService::eagerLoads())->findOrFail($id);
        $data = $request->validated();
        $attachments = $request->hasFile('attachments') ? $request->file('attachments') : null;

        try {
            $expense = $this->expenses->update(
                $item,
                $data,
                is_array($attachments) ? $attachments : ($attachments ? [$attachments] : null),
            );

            return $this->responder(__('messages.api.updated'), 200, new ExpenseResource($expense))->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function destroy(string $id)
    {
        Expense::findOrFail($id);

        return $this->responder(__('messages.api.permission_denied'), 403)->respond();
    }

    public function prefill()
    {
        return $this->responder(__('messages.api.retrieved'), 200, $this->expenses->prefill())->respond();
    }

    public function taxPreview(PreviewExpenseTaxRequest $request)
    {
        $data = $request->validated();

        return $this->responder(__('messages.api.retrieved'), 200, $this->expenses->taxPreview(
            (float) $data['amount'],
            isset($data['tax_profile_id']) ? (int) $data['tax_profile_id'] : null,
            (bool) ($data['amount_includes_tax'] ?? false),
        ))->respond();
    }

    public function overview()
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'cards' => $this->expenses->overview(),
        ])->respond();
    }

    public function treasuryAccounts()
    {
        $codes = array_keys(Acc4::collectionAccountOptions());
        $data = Acc4::query()->whereIn('code', $codes)->orderBy('name')->get();

        return $this->responder(__('messages.api.retrieved'), 200, Acc4Resource::collection($data))->respond();
    }
}
